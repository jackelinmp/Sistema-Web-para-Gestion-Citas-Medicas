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
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta, c.paciente_id,
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

    /**
     * CU15/CU17: agenda de una fecha, opcionalmente filtrada por estado y por médico.
     * Reutiliza vw_citas_detalle para no repetir los JOIN de paciente y especialidad.
     */
    public function listarAgenda(string $fecha, ?string $estado, ?int $medicoId): array
    {
        $sql = "SELECT id, codigo, fecha, hora_inicio, hora_fin, estado, paciente, medico_id, medico,
                       especialidad, motivo_consulta
                  FROM vw_citas_detalle
                 WHERE fecha = CAST(:fecha AS date)";
        $parametros = ['fecha' => $fecha];
        if ($estado !== null && $estado !== '') {
            $sql .= ' AND estado = :estado';
            $parametros['estado'] = $estado;
        }
        if ($medicoId !== null) {
            $sql .= ' AND medico_id = :medico';
            $parametros['medico'] = $medicoId;
        }
        $st = $this->bd->prepare($sql . ' ORDER BY hora_inicio');
        $st->execute($parametros);
        return $st->fetchAll();
    }

    /** CU15/CU16/CU17: datos mínimos de la cita que se va a modificar. */
    public function obtenerParaGestion(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, codigo, paciente_id, medico_id, fecha, hora_inicio, estado
                                    FROM cita WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /**
     * CU15/CU17: cambia el estado con el procedimiento de la base.
     * Él valida la transición permitida y registra el evento en la bitácora.
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $usuarioId): void
    {
        $st = $this->bd->prepare('SELECT sp_cambiar_estado_cita(:id, :estado, :usuario)');
        $st->execute(['id' => $id, 'estado' => $nuevoEstado, 'usuario' => $usuarioId]);
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
