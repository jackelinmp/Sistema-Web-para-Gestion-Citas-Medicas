<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** Â«entityÂ» Cita â€” tabla cita (CU08, CU11, CU13, CU14, CU15, CU16, CU17, CU18). */
final class Cita extends EntidadBase
{
    /** Citas activas a futuro de una especialidad (excepciÃ³n "desactivar con citas programadas" de CU08). */
    public function contarProgramadasPorEspecialidad(int $especialidad_id): int
    {
        $st = $this->bd->prepare("SELECT count(*) FROM cita
                                   WHERE especialidad_id = :esp AND estado IN ('PROGRAMADA', 'CONFIRMADA')
                                     AND fecha >= current_date");
        $st->execute(['esp' => $especialidad_id]);
        return (int) $st->fetchColumn();
    }

    /** CU13: cupos libres de un mÃ©dico en una fecha (jornada - citas activas - bloqueos - horas pasadas). */
    public function cuposDisponibles(int $medico_id, string $fecha): array
    {
        $st = $this->bd->prepare('SELECT hora_inicio, hora_fin FROM fn_cupos_disponibles(:medico, CAST(:fecha AS date))');
        $st->execute(['medico' => $medico_id, 'fecha' => $fecha]);
        return $st->fetchAll();
    }

    /** CU14: registra la cita en estado PROGRAMADA. Devuelve id y cÃ³digo. */
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

    /** CU14 y CU16: datos completos de una cita (constancia y pantalla de cambio). */
    public function obtenerDetalle(int $id): ?array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo_consulta, c.paciente_id,
                                         c.medico_id, c.especialidad_id, c.veces_reprogramada, c.motivo_cancelacion,
                                         pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente,
                                         pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
                                         e.costo_consulta, pg.estado AS pago_estado, pg.monto
                                    FROM cita c
                                    JOIN paciente pa ON pa.persona_id = c.paciente_id
                                    JOIN persona pp  ON pp.id = c.paciente_id
                                    JOIN persona pm  ON pm.id = c.medico_id
                                    JOIN especialidad e ON e.id = c.especialidad_id
                                    LEFT JOIN pago pg ON pg.cita_id = c.id
                                   WHERE c.id = :id");
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /**
     * CU15/CU17: agenda de una fecha, opcionalmente filtrada por estado y por mÃ©dico.
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

    /** CU15/CU16/CU17: datos de la cita que se va a modificar. */
    public function obtenerParaGestion(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
                                         estado, veces_reprogramada
                                    FROM cita WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /**
     * CU15/CU17: cambia el estado con el procedimiento de la base.
     * Ã‰l valida la transiciÃ³n permitida y registra el evento en la bitÃ¡cora.
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $usuarioId): void
    {
        $st = $this->bd->prepare('SELECT sp_cambiar_estado_cita(:id, :estado, :usuario)');
        $st->execute(['id' => $id, 'estado' => $nuevoEstado, 'usuario' => $usuarioId]);
    }

    /**
     * CU11 flujo alternativo: citas futuras activas del mÃ©dico que quedan fuera de su jornada vigente.
     * Se llama dentro de la transacciÃ³n, despuÃ©s de guardar la jornada nueva.
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

    // ------------------------------------------------------------------ CU16 Reprogramar y cancelar

    /**
     * CU16: citas activas desde hoy (Programada o Confirmada).
     * $pacienteId limita a un paciente (el rol PACIENTE solo ve las suyas); $criterio busca por paciente, CI o cÃ³digo.
     */
    public function listarProximas(?int $pacienteId, string $criterio = ''): array
    {
        $st = $this->bd->prepare("SELECT id, codigo, fecha, hora_inicio, hora_fin, estado, paciente, paciente_ci, medico,
                                         especialidad, pago_estado, veces_reprogramada
                                    FROM vw_citas_detalle
                                   WHERE estado IN ('PROGRAMADA', 'CONFIRMADA') AND fecha >= current_date
                                     AND (CAST(:p AS bigint) IS NULL OR paciente_id = :p2)
                                     AND (CAST(:c AS text) = '' OR paciente ILIKE :l1 OR paciente_ci ILIKE :l2 OR codigo ILIKE :l3)
                                   ORDER BY fecha, hora_inicio
                                   LIMIT 100");
        $like = '%' . $criterio . '%';
        $st->execute(['p' => $pacienteId, 'p2' => $pacienteId, 'c' => $criterio, 'l1' => $like, 'l2' => $like, 'l3' => $like]);
        return $st->fetchAll();
    }

    /** CU16: mueve la misma cita a otro mÃ©dico, fecha y hora (sp_reprogramar_cita valida estado y fecha). */
    public function reprogramar(int $id, int $medicoId, string $fecha, string $horaInicio, string $horaFin, int $usuarioId): void
    {
        $this->bd->prepare('SELECT sp_reprogramar_cita(:id, :medico, CAST(:fecha AS date), CAST(:ini AS time), CAST(:fin AS time), :usuario)')
                 ->execute(['id' => $id, 'medico' => $medicoId, 'fecha' => $fecha, 'ini' => $horaInicio,
                            'fin' => $horaFin, 'usuario' => $usuarioId]);
    }

    /** CU16: guarda el motivo y pasa la cita a CANCELADA (el pago se anula o queda para reembolso). */
    public function cancelar(int $id, string $motivo, int $usuarioId): void
    {
        $this->bd->prepare('UPDATE cita SET motivo_cancelacion = :motivo WHERE id = :id')
                 ->execute(['motivo' => $motivo, 'id' => $id]);
        $this->cambiarEstado($id, 'CANCELADA', $usuarioId);
    }

    // ------------------------------------------------------------------ CU18 Reportes operativos

    /** CU18: totales del periodo por estado. */
    public function resumirPeriodo(string $desde, string $hasta): array
    {
        $st = $this->bd->prepare("SELECT count(*) AS citas,
                                         count(*) FILTER (WHERE estado = 'ATENDIDA')     AS atendidas,
                                         count(*) FILTER (WHERE estado = 'CANCELADA')    AS canceladas,
                                         count(*) FILTER (WHERE estado = 'INASISTENCIA') AS inasistencias,
                                         count(*) FILTER (WHERE estado IN ('PROGRAMADA', 'CONFIRMADA')) AS pendientes
                                    FROM cita WHERE fecha BETWEEN CAST(:d AS date) AND CAST(:h AS date)");
        $st->execute(['d' => $desde, 'h' => $hasta]);
        return $st->fetch();
    }

    /** CU18: productividad por mÃ©dico (citas por estado, % de inasistencia e ingresos cobrados). */
    public function reportarProductividad(string $desde, string $hasta): array
    {
        $st = $this->bd->prepare("SELECT p.nombres || ' ' || p.apellidos AS medico,
                                         count(c.id) AS citas,
                                         count(c.id) FILTER (WHERE c.estado = 'ATENDIDA')     AS atendidas,
                                         count(c.id) FILTER (WHERE c.estado = 'CANCELADA')    AS canceladas,
                                         count(c.id) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias,
                                         round(100.0 * count(c.id) FILTER (WHERE c.estado = 'INASISTENCIA')
                                               / NULLIF(count(c.id) FILTER (WHERE c.estado IN ('ATENDIDA', 'INASISTENCIA')), 0), 1) AS pct_inasistencia,
                                         COALESCE(sum(pg.monto) FILTER (WHERE pg.estado = 'PAGADO'), 0) AS ingresos
                                    FROM medico m
                                    JOIN persona p ON p.id = m.persona_id
                                    JOIN cita c ON c.medico_id = m.persona_id
                                               AND c.fecha BETWEEN CAST(:d AS date) AND CAST(:h AS date)
                                    LEFT JOIN pago pg ON pg.cita_id = c.id
                                   GROUP BY m.persona_id, p.nombres, p.apellidos
                                   ORDER BY atendidas DESC, medico");
        $st->execute(['d' => $desde, 'h' => $hasta]);
        return $st->fetchAll();
    }

    /** CU18 (alimentado por CU17): inasistencias por especialidad sobre las citas que ya debÃ­an ocurrir. */
    public function reportarAusentismo(string $desde, string $hasta): array
    {
        $st = $this->bd->prepare("SELECT e.nombre AS especialidad,
                                         count(*) AS citas_vencidas,
                                         count(*) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias,
                                         round(100.0 * count(*) FILTER (WHERE c.estado = 'INASISTENCIA') / count(*), 1) AS pct_inasistencia
                                    FROM cita c
                                    JOIN especialidad e ON e.id = c.especialidad_id
                                   WHERE c.fecha BETWEEN CAST(:d AS date) AND CAST(:h AS date)
                                     AND c.estado IN ('ATENDIDA', 'INASISTENCIA')
                                   GROUP BY e.nombre
                                   ORDER BY pct_inasistencia DESC, especialidad");
        $st->execute(['d' => $desde, 'h' => $hasta]);
        return $st->fetchAll();
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

    /** CU10: citas futuras activas del m+¬dico (impiden darlo de baja hasta reprogramarlas o cancelarlas). */
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

    /** CU12: citas activas del m+¬dico dentro del periodo [inicio, fin) que se quiere bloquear. */
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

    /** CU23: citas CONFIRMADAS o ATENDIDAS del paciente (las +¦nicas en que la base acepta solicitar ex+ímenes). */
    public function listarParaExamen(int $paciente_id): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.estado, e.nombre AS especialidad
                                    FROM cita c JOIN especialidad e ON e.id = c.especialidad_id
                                   WHERE c.paciente_id = :p AND c.estado IN ('CONFIRMADA', 'ATENDIDA')
                                   ORDER BY c.fecha DESC LIMIT 50");
        $st->execute(['p' => $paciente_id]);
        return $st->fetchAll();
    }
}
