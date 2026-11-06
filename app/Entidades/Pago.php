<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Pago — tablas pago y comprobante. CU14 registra el pago PENDIENTE; CU20 consulta pagos y comprobantes. */
final class Pago extends EntidadBase
{
    public function insertar(int $cita_id, string $monto, int $registrado_por): void
    {
        $this->bd->prepare('INSERT INTO pago (cita_id, monto, registrado_por) VALUES (:cita, :monto, :usuario)')
                 ->execute(['cita' => $cita_id, 'monto' => $monto, 'usuario' => $registrado_por]);
    }

    // ===================== CICLO 2 · Jackeline (CU20) =====================
    private const DETALLE = "SELECT pg.id AS pago_id, pg.monto, pg.moneda, pg.estado AS pago_estado, pg.metodo, pg.referencia_externa,
                                    pg.fecha_pago, c.id AS cita_id, c.codigo, c.fecha, c.hora_inicio, c.paciente_id,
                                    pp.nombres || ' ' || pp.apellidos AS paciente, pp.ci, pa.nro_expediente,
                                    pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
                                    co.nro_correlativo, co.fecha_emision
                               FROM pago pg
                               JOIN cita c ON c.id = pg.cita_id
                               JOIN paciente pa ON pa.persona_id = c.paciente_id
                               JOIN persona pp ON pp.id = c.paciente_id
                               JOIN persona pm ON pm.id = c.medico_id
                               JOIN especialidad e ON e.id = c.especialidad_id
                               LEFT JOIN comprobante co ON co.pago_id = pg.id ";

    /**
     * Historial de pagos con filtros. $f['pacientes'] = ids a mostrar (paciente: él y su grupo familiar),
     * null = todos (personal). Incluye el nro. de comprobante cuando existe.
     */
    public function listarHistorial(array $f): array
    {
        $ids = $f['pacientes'] === null ? null : '{' . implode(',', array_map('intval', $f['pacientes'])) . '}';
        $like = '%' . $f['criterio'] . '%';
        $st = $this->bd->prepare(self::DETALLE . "WHERE (CAST(:ids AS bigint[]) IS NULL OR c.paciente_id = ANY (CAST(:ids2 AS bigint[])))
                                   AND (CAST(:e AS text) = '' OR pg.estado = :e2)
                                   AND (CAST(:m AS text) = '' OR pg.metodo = :m2)
                                   AND (CAST(:k AS text) = '' OR pp.nombres || ' ' || pp.apellidos ILIKE :k2 OR c.codigo ILIKE :k3
                                        OR pa.nro_expediente ILIKE :k4 OR co.nro_correlativo ILIKE :k5)
                                   AND (CAST(:d AS date) IS NULL OR COALESCE(pg.fecha_pago, pg.fecha_registro)::date >= CAST(:d2 AS date))
                                   AND (CAST(:h AS date) IS NULL OR COALESCE(pg.fecha_pago, pg.fecha_registro)::date <= CAST(:h2 AS date))
                                 ORDER BY COALESCE(pg.fecha_pago, pg.fecha_registro) DESC LIMIT 300");
        $st->execute(['ids' => $ids, 'ids2' => $ids, 'e' => $f['estado'], 'e2' => $f['estado'], 'm' => $f['metodo'], 'm2' => $f['metodo'],
                      'k' => $f['criterio'], 'k2' => $like, 'k3' => $like, 'k4' => $like, 'k5' => $like,
                      'd' => $f['desde'] ?: null, 'd2' => $f['desde'] ?: null, 'h' => $f['hasta'] ?: null, 'h2' => $f['hasta'] ?: null]);
        return $st->fetchAll();
    }

    /** Pago de una cita con sus datos y su comprobante (si existe). */
    public function obtenerPorCita(int $cita_id): ?array
    {
        $st = $this->bd->prepare(self::DETALLE . 'WHERE pg.cita_id = :c');
        $st->execute(['c' => $cita_id]);
        return $st->fetch() ?: null;
    }
}
