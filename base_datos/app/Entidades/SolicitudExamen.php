<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» SolicitudExamen — tabla solicitud_examen (CU23).
 * Estados: SOLICITADO -> EN_PROCESO -> RESULTADO_LISTO (lo pone el trigger tg_resultado_listo) -> ENTREGADO
 *          SOLICITADO / EN_PROCESO -> CANCELADO
 * La base valida (tg_solicitud_examen_valida) que la cita esté CONFIRMADA o ATENDIDA y que el examen esté activo,
 * y copia el costo del catálogo.
 */
final class SolicitudExamen extends EntidadBase
{
    private const SELECT = "SELECT s.id, s.estado, s.costo, s.fecha_solicitud, t.nombre AS examen,
                                   c.id AS cita_id, c.codigo AS cita_codigo, c.fecha AS cita_fecha, c.paciente_id,
                                   pm.nombres || ' ' || pm.apellidos AS medico,
                                   pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente,
                                   r.id AS resultado_id, r.resultado, r.archivo_nombre, r.tipo_archivo, r.fecha_resultado
                              FROM solicitud_examen s
                              JOIN tipo_examen t ON t.id = s.tipo_examen_id
                              JOIN cita c        ON c.id = s.cita_id
                              JOIN persona pm    ON pm.id = c.medico_id
                              JOIN persona pp    ON pp.id = c.paciente_id
                              JOIN paciente pa   ON pa.persona_id = c.paciente_id
                              LEFT JOIN resultado_laboratorio r ON r.solicitud_examen_id = s.id";

    public function listarPorPaciente(int $paciente_id, bool $incluirCancelados = true): array
    {
        $st = $this->bd->prepare(self::SELECT . " WHERE c.paciente_id = :p AND (CAST(:todos AS boolean) OR s.estado <> 'CANCELADO')
                                                  ORDER BY s.fecha_solicitud DESC");
        $st->execute(['p' => $paciente_id, 'todos' => $incluirCancelados ? 'true' : 'false']);
        return $st->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare(self::SELECT . ' WHERE s.id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /** sp_solicitar_examen: inserta la solicitud y la registra en la bitácora. Devuelve el id. */
    public function solicitar(int $cita_id, int $tipo_examen_id, int $usuario_id): int
    {
        $st = $this->bd->prepare('SELECT sp_solicitar_examen(:c, CAST(:t AS smallint), :u)');
        $st->execute(['c' => $cita_id, 't' => $tipo_examen_id, 'u' => $usuario_id]);
        return (int) $st->fetchColumn();
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->bd->prepare('UPDATE solicitud_examen SET estado = :e WHERE id = :id')->execute(['e' => $estado, 'id' => $id]);
    }
}
