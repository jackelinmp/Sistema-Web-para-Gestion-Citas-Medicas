<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Cita — tabla cita (CU08, CU11, CU13, CU14, CU15, CU16, CU17, CU18). */
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
        $this->anularCitasSinPago();     // una cita en línea sin pagar no ocupa el horario
        $st = $this->bd->prepare('SELECT hora_inicio, hora_fin FROM fn_cupos_disponibles(:medico, CAST(:fecha AS date))');
        $st->execute(['medico' => $medico_id, 'fecha' => $fecha]);
        return $st->fetchAll();
    }

    /**
     * CU14/CU19/CU20: la cita solo se confirma pagando. Mientras se paga, el horario se aparta
     * durante MINUTOS_PLAZO_PAGO (por defecto 30) para que nadie más lo pague al mismo tiempo (así no hay
     * devoluciones). Si no paga en ese plazo, la cita se anula, el pago queda Anulado y el horario vuelve a estar libre.
     * Si el paciente ya abrió el pago en Stripe (la sesión vence a los 31 minutos), se le dan 35 minutos más.
     * Aplica a las citas en línea (portal y asistente) y a las de recepción, que se pagan en ese momento en ventanilla.
     * No aplica a la cita que saca el médico: esa queda con pago pendiente hasta que el paciente la pague.
     * Devuelve cuántas citas se anularon.
     */
    public function anularCitasSinPago(): int
    {
        $st = $this->bd->query("SELECT c.id, c.registrado_por
                                  FROM cita c
                                  JOIN pago p ON p.cita_id = c.id
                                 WHERE c.estado = 'PROGRAMADA' AND p.estado = 'PENDIENTE'
                                   AND c.origen <> 'MEDICO'
                                   AND c.fecha_registro < now() - make_interval(mins =>
                                         COALESCE((SELECT NULLIF(valor, '')::int FROM parametro_sistema
                                                    WHERE clave = 'MINUTOS_PLAZO_PAGO'), 30)
                                         + CASE WHEN p.referencia_externa IS NULL THEN 0 ELSE 35 END)
                                   FOR UPDATE OF c SKIP LOCKED");
        $vencidas = $st->fetchAll();
        foreach ($vencidas as $c) {
            $this->bd->prepare("UPDATE cita SET motivo_cancelacion = 'No se pagó dentro del plazo: el horario quedó libre'
                                 WHERE id = :id")->execute(['id' => $c['id']]);
            $this->cambiarEstado((int) $c['id'], 'CANCELADA', (int) $c['registrado_por']);
        }
        return count($vencidas);
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
     * CU15/CU17: agenda de una fecha, opcionalmente filtrada por estado y por médico.
     * Reutiliza vw_citas_detalle para no repetir los JOIN de paciente y especialidad.
     */
    public function listarAgenda(string $fecha, ?string $estado, ?int $medicoId): array
    {
        // telefono_aviso: celular del paciente o, si no tiene (un menor, un familiar), el de quien saca sus citas.
        $sql = "SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.paciente, c.medico_id, c.medico,
                       c.especialidad, c.motivo_consulta,
                       COALESCE(pp.telefono, pt.telefono,
                                (SELECT tv.telefono FROM vinculo_familiar vf JOIN persona tv ON tv.id = vf.paciente_id
                                  WHERE vf.familiar_id = c.paciente_id AND tv.telefono IS NOT NULL
                                  ORDER BY vf.id LIMIT 1)) AS telefono_aviso
                  FROM vw_citas_detalle c
                  JOIN persona pp ON pp.id = c.paciente_id
                  LEFT JOIN persona pt ON pt.id = pp.tutor_id
                 WHERE c.fecha = CAST(:fecha AS date)";
        $parametros = ['fecha' => $fecha];
        if ($estado !== null && $estado !== '') {
            $sql .= ' AND c.estado = :estado';
            $parametros['estado'] = $estado;
        }
        if ($medicoId !== null) {
            $sql .= ' AND c.medico_id = :medico';
            $parametros['medico'] = $medicoId;
        }
        $st = $this->bd->prepare($sql . ' ORDER BY c.hora_inicio');
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

    // ------------------------------------------------------------------ CU16 Reprogramar y cancelar

    /**
     * CU16: citas activas desde hoy (Programada o Confirmada).
     * $pacienteId limita a un paciente o lista (el rol PACIENTE ve las suyas y las de su grupo familiar); $criterio busca por paciente, CI o código.
     */
    public function listarProximas(int|array|null $pacienteId, string $criterio = ''): array
    {
        $filtro = '';
        $params = ['c' => $criterio, 'l1' => '%' . $criterio . '%', 'l2' => '%' . $criterio . '%', 'l3' => '%' . $criterio . '%'];
        if (is_array($pacienteId)) {
            $ids = array_values(array_unique(array_map('intval', $pacienteId)));
            $filtro = $ids ? ' AND paciente_id IN (' . implode(',', $ids) . ')' : ' AND FALSE';
            $params['p'] = null;
            $params['p2'] = null;
        } else {
            $params['p'] = $pacienteId;
            $params['p2'] = $pacienteId;
        }
        $st = $this->bd->prepare("SELECT id, codigo, fecha, hora_inicio, hora_fin, estado, paciente, paciente_ci, medico,
                                         especialidad, pago_estado, veces_reprogramada
                                    FROM vw_citas_detalle
                                   WHERE estado IN ('PROGRAMADA', 'CONFIRMADA') AND fecha >= current_date
                                     AND (CAST(:p AS bigint) IS NULL OR paciente_id = :p2)
                                     AND (CAST(:c AS text) = '' OR paciente ILIKE :l1 OR paciente_ci ILIKE :l2 OR codigo ILIKE :l3)
                                     $filtro
                                   ORDER BY fecha, hora_inicio
                                   LIMIT 100");
        $st->execute($params);
        return $st->fetchAll();
    }

    /** CU16: mueve la misma cita a otro médico, fecha y hora (sp_reprogramar_cita valida estado y fecha). */
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

    /** CU18: productividad por médico (citas por estado, % de inasistencia e ingresos cobrados). */
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

    /** CU18 (alimentado por CU17): inasistencias por especialidad sobre las citas que ya debían ocurrir. */
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
     * CU18 (RF13): citas de un periodo con filtros opcionales por estado, médico y especialidad.
     * Cubre «citas de una fecha», «por médico», «por especialidad», «por estado» y la agenda diaria de un médico.
     */
    public function listarParaReporte(string $desde, string $hasta, string $estado = '', ?int $medicoId = null, ?int $especialidadId = null): array
    {
        $sql = "SELECT to_char(fecha, 'DD/MM/YYYY') AS fecha, to_char(hora_inicio, 'HH24:MI') AS hora, codigo, paciente, paciente_ci,
                       medico, especialidad, estado
                  FROM vw_citas_detalle
                 WHERE fecha BETWEEN CAST(:d AS date) AND CAST(:h AS date)";
        $p = ['d' => $desde, 'h' => $hasta];
        if ($estado !== '') {
            $sql .= ' AND estado = :e';
            $p['e'] = $estado;
        }
        if ($medicoId) {
            $sql .= ' AND medico_id = :m';
            $p['m'] = $medicoId;
        }
        if ($especialidadId) {
            $sql .= ' AND especialidad_id = :s';
            $p['s'] = $especialidadId;
        }
        $st = $this->bd->prepare($sql . ' ORDER BY vw_citas_detalle.fecha, hora_inicio, medico LIMIT 2000');
        $st->execute($p);
        return $st->fetchAll();
    }
}
