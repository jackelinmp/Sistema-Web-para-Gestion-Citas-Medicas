<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» EventoPaciente — tabla evento_paciente (bandeja de eventos).
 * La llenan los triggers de la base (cambio de estado de cita, resultado listo) y sp_reprogramar_cita.
 * CU24 la lee para generar las notificaciones.
 */
final class EventoPaciente extends EntidadBase
{
    /** Los eventos de más de 48 horas ya no sirven como aviso: se cierran sin notificar. Devuelve cuántos cerró. */
    public function cerrarAntiguos(): int
    {
        return $this->bd->exec("UPDATE evento_paciente SET procesado = TRUE
                                 WHERE NOT procesado AND fecha_evento < now() - interval '48 hours'");
    }

    /** Eventos sin procesar, con la cuenta del paciente (si tiene) y los datos actuales de la cita. */
    public function listarPendientes(int $limite = 200): array
    {
        $st = $this->bd->prepare("SELECT e.id, e.tipo_evento, e.payload, u.id AS usuario_id, c.id AS cita_id, c.codigo, c.fecha, c.hora_inicio,
                                         c.medico, c.especialidad, c.motivo_cancelacion, c.pago_estado
                                    FROM evento_paciente e
                                    LEFT JOIN usuario u ON u.persona_id = e.paciente_id AND u.estado = 'HABILITADA'
                                    LEFT JOIN vw_citas_detalle c ON c.id = CAST(e.payload->>'cita_id' AS bigint)
                                   WHERE NOT e.procesado
                                   ORDER BY e.id LIMIT " . (int) $limite);
        $st->execute();
        return $st->fetchAll();
    }

    public function marcarProcesado(int $id): void
    {
        $this->bd->prepare('UPDATE evento_paciente SET procesado = TRUE WHERE id = :id')->execute(['id' => $id]);
    }
}
