<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Cita — tabla cita (CU08, CU11, CU13, CU14). */
final class Cita extends EntidadBase
{
    /** Citas activas a futuro de una especialidad (excepción "desactivar con citas programadas" de CU08). */
    public function contarProgramadasPorEspecialidad(int $especialidad_id): int
    {
        $st = $this->bd->prepare("SELECT count(*) FROM cita
                                   WHERE especialidad_id = :esp AND estado IN ('PROGRAMADA', 'CONFIRMADA')
                                     AND fecha >= current_date");
        $st->execute(['esp' => $especialidad_id]);
        return (int) $st->fetchColumn();
    }

    /** CU13: cupos libres de un médico en una fecha (jornada - citas activas - bloqueos - horas pasadas). */
    public function cuposDisponibles(int $medico_id, string $fecha): array
    {
        $st = $this->bd->prepare('SELECT hora_inicio, hora_fin FROM fn_cupos_disponibles(:medico, CAST(:fecha AS date))');
        $st->execute(['medico' => $medico_id, 'fecha' => $fecha]);
        return $st->fetchAll();
    }

    /** CU14: registra la cita en estado PROGRAMADA. Devuelve id y código. */
    public function insertar(array $d): array
    {
        $st = $this->bd->prepare('INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
                                                    motivo_consulta, origen, registrado_por)
                                  VALUES (:paciente_id, :medico_id, :especialidad_id, :fecha, :hora_inicio, :hora_fin,
                                          :motivo_consulta, :origen, :registrado_por)
                                  RETURNING id, codigo');
        $st->execute($d);
        return $st->fetch();
    }

    /** CU14: datos de la constancia de la cita. */
    public function obtenerDetalle(int $id): ?array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
                                         pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente,
                                         pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
                                         e.costo_consulta
                                    FROM cita c
                                    JOIN paciente pa ON pa.persona_id = c.paciente_id
                                    JOIN persona pp  ON pp.id = c.paciente_id
                                    JOIN persona pm  ON pm.id = c.medico_id
                                    JOIN especialidad e ON e.id = c.especialidad_id
                                   WHERE c.id = :id");
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    // ===================== CICLO 2 · Jackeline =====================
    /** CU07: citas del paciente (todas las de su historial) con filtros opcionales de fecha y estado. */
    public function listarPorPaciente(int $paciente_id, string $desde = '', string $hasta = '', string $estado = ''): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta,
                                         pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
                                         pg.estado AS estado_pago, pg.monto
                                    FROM cita c
                                    JOIN persona pm     ON pm.id = c.medico_id
                                    JOIN especialidad e ON e.id = c.especialidad_id
                                    LEFT JOIN pago pg   ON pg.cita_id = c.id
                                   WHERE c.paciente_id = :p
                                     AND (CAST(:d1 AS date) IS NULL OR c.fecha >= CAST(:d2 AS date))
                                     AND (CAST(:h1 AS date) IS NULL OR c.fecha <= CAST(:h2 AS date))
                                     AND (CAST(:e1 AS text) = '' OR c.estado = :e2)
                                   ORDER BY c.fecha DESC, c.hora_inicio DESC");
        $st->execute(['p' => $paciente_id, 'd1' => $desde ?: null, 'd2' => $desde ?: null,
                      'h1' => $hasta ?: null, 'h2' => $hasta ?: null, 'e1' => $estado, 'e2' => $estado]);
        return $st->fetchAll();
    }

    /** CU10: citas futuras activas del médico (impiden darlo de baja hasta reprogramarlas o cancelarlas). */
    public function listarPendientesPorMedico(int $medico_id): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.estado,
                                         pp.nombres || ' ' || pp.apellidos AS paciente, pp.telefono
                                    FROM cita c JOIN persona pp ON pp.id = c.paciente_id
                                   WHERE c.medico_id = :m AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                                     AND c.fecha >= current_date
                                   ORDER BY c.fecha, c.hora_inicio");
        $st->execute(['m' => $medico_id]);
        return $st->fetchAll();
    }

    /** CU12: citas activas del médico dentro del periodo [inicio, fin) que se quiere bloquear. */
    public function listarEnPeriodo(int $medico_id, string $inicio, string $fin): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, pp.nombres || ' ' || pp.apellidos AS paciente, pp.telefono
                                    FROM cita c JOIN persona pp ON pp.id = c.paciente_id
                                   WHERE c.medico_id = :m AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                                     AND tsrange(c.fecha + c.hora_inicio, c.fecha + c.hora_fin)
                                         && tsrange(CAST(:i AS timestamp), CAST(:f AS timestamp))
                                   ORDER BY c.fecha, c.hora_inicio");
        $st->execute(['m' => $medico_id, 'i' => $inicio, 'f' => $fin]);
        return $st->fetchAll();
    }

    /** CU23: citas CONFIRMADAS o ATENDIDAS del paciente (las únicas en que la base acepta solicitar exámenes). */
    public function listarParaExamen(int $paciente_id): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.estado, e.nombre AS especialidad
                                    FROM cita c JOIN especialidad e ON e.id = c.especialidad_id
                                   WHERE c.paciente_id = :p AND c.estado IN ('CONFIRMADA', 'ATENDIDA')
                                   ORDER BY c.fecha DESC LIMIT 50");
        $st->execute(['p' => $paciente_id]);
        return $st->fetchAll();
    }

    /**
     * CU11 flujo alternativo: citas futuras activas del médico que quedan fuera de su jornada vigente.
     * Se llama dentro de la transacción, después de guardar la jornada nueva.
     */
    public function contarFueraDeJornada(int $medico_id): int
    {
        $st = $this->bd->prepare("SELECT count(*) FROM cita c
                                   WHERE c.medico_id = :medico AND c.estado IN ('PROGRAMADA','CONFIRMADA')
                                     AND c.fecha >= current_date
                                     AND NOT EXISTS (SELECT 1 FROM jornada j
                                                      WHERE j.medico_id = c.medico_id AND j.vigente
                                                        AND j.dia_semana = EXTRACT(ISODOW FROM c.fecha)
                                                        AND c.hora_inicio >= j.hora_inicio AND c.hora_fin <= j.hora_fin)");
        $st->execute(['medico' => $medico_id]);
        return (int) $st->fetchColumn();
    }
}
