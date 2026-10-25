<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Pago — tablas pago y comprobante. CU14 crea el pago PENDIENTE; CU19 lo cobra y emite el comprobante. */
final class Pago extends EntidadBase
{
    private const DETALLE = "SELECT pg.id AS pago_id, pg.monto, pg.moneda, pg.estado AS pago_estado, pg.metodo, pg.referencia_externa,
                                    pg.fecha_pago, pg.registrado_por,
                                    c.id AS cita_id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado AS cita_estado, c.paciente_id,
                                    pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente, pa.nro_poliza, pa.aseguradora_id,
                                    ase.nombre AS aseguradora,
                                    pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
                                    co.nro_correlativo, co.fecha_emision
                               FROM pago pg
                               JOIN cita c ON c.id = pg.cita_id
                               JOIN paciente pa ON pa.persona_id = c.paciente_id
                               JOIN persona pp ON pp.id = c.paciente_id
                               JOIN persona pm ON pm.id = c.medico_id
                               JOIN especialidad e ON e.id = c.especialidad_id
                               LEFT JOIN aseguradora ase ON ase.id = pa.aseguradora_id
                               LEFT JOIN comprobante co ON co.pago_id = pg.id ";

    public function insertar(int $cita_id, string $monto, int $registrado_por): void
    {
        $this->bd->prepare('INSERT INTO pago (cita_id, monto, registrado_por) VALUES (:cita, :monto, :usuario)')
                 ->execute(['cita' => $cita_id, 'monto' => $monto, 'usuario' => $registrado_por]);
    }

    /** Pago de una cita con todos los datos para la pantalla y el comprobante. */
    public function obtenerPorCita(int $citaId, bool $bloquear = false): ?array
    {
        $st = $this->bd->prepare(self::DETALLE . 'WHERE pg.cita_id = :c' . ($bloquear ? ' FOR UPDATE OF pg' : ''));
        $st->execute(['c' => $citaId]);
        return $st->fetch() ?: null;
    }

    /** Lista de pagos; $soloPaciente limita a un paciente (el rol PACIENTE solo ve los suyos). */
    public function listar(string $estado, ?int $soloPaciente, string $criterio = ''): array
    {
        $st = $this->bd->prepare(self::DETALLE . "WHERE (CAST(:e AS text) = '' OR pg.estado = :e2)
                                   AND (CAST(:p AS bigint) IS NULL OR c.paciente_id = :p2)
                                   AND (CAST(:k AS text) = '' OR pp.nombres || ' ' || pp.apellidos ILIKE :k2 OR c.codigo ILIKE :k3)
                                 ORDER BY (pg.estado = 'PENDIENTE') DESC, c.fecha DESC, c.hora_inicio DESC LIMIT 100");
        $like = '%' . $criterio . '%';
        $st->execute(['e' => $estado, 'e2' => $estado, 'p' => $soloPaciente, 'p2' => $soloPaciente,
                      'k' => $criterio, 'k2' => $like, 'k3' => $like]);
        return $st->fetchAll();
    }

    public function guardarReferencia(int $citaId, string $referencia): void
    {
        $this->bd->prepare("UPDATE pago SET referencia_externa = :r WHERE cita_id = :c AND estado = 'PENDIENTE'")
                 ->execute(['r' => $referencia, 'c' => $citaId]);
    }

    /** Cobra el pago pendiente y emite el comprobante (sp_registrar_pago). Devuelve el nro. de comprobante. */
    public function registrarPago(int $citaId, string $metodo, int $usuario, ?string $referencia): string
    {
        $st = $this->bd->prepare('SELECT sp_registrar_pago(:c, :m, :u, :r)');
        $st->execute(['c' => $citaId, 'm' => $metodo, 'u' => $usuario, 'r' => $referencia]);
        return (string) $st->fetchColumn();
    }

    public function confirmarCita(int $citaId, int $usuario): void
    {
        $this->bd->prepare("SELECT sp_cambiar_estado_cita(:c, 'CONFIRMADA', :u)")->execute(['c' => $citaId, 'u' => $usuario]);
    }
}
