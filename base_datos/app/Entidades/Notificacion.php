<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Notificacion — tabla notificacion (CU24). Una fila por aviso al usuario; se envía por correo y se ve en pantalla. */
final class Notificacion extends EntidadBase
{
    public function registrar(int $usuarioId, ?int $citaId, string $tipo, string $asunto, string $mensaje): int
    {
        $st = $this->bd->prepare("INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje)
                                  VALUES (:u, :c, :t, 'CORREO', :a, :m) RETURNING id");
        $st->execute(['u' => $usuarioId, 'c' => $citaId, 't' => $tipo, 'a' => $asunto, 'm' => $mensaje]);
        return (int) $st->fetchColumn();
    }

    /** Avisos de un usuario, los no leídos primero. */
    public function listarPorUsuario(int $usuarioId): array
    {
        $st = $this->bd->prepare('SELECT id, tipo, asunto, mensaje, leida, fecha_creacion, fecha_envio
                                    FROM notificacion WHERE usuario_id = :u
                                   ORDER BY leida, fecha_creacion DESC LIMIT 100');
        $st->execute(['u' => $usuarioId]);
        return $st->fetchAll();
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        $st = $this->bd->prepare('SELECT count(*) FROM notificacion WHERE usuario_id = :u AND NOT leida');
        $st->execute(['u' => $usuarioId]);
        return (int) $st->fetchColumn();
    }

    /** Solo el dueño puede marcarla (el usuario va en el WHERE). */
    public function marcarLeida(int $id, int $usuarioId): void
    {
        $this->bd->prepare('UPDATE notificacion SET leida = TRUE WHERE id = :id AND usuario_id = :u')
                 ->execute(['id' => $id, 'u' => $usuarioId]);
    }

    /** Correos aún no enviados, con el destinatario. */
    public function listarSinEnviar(int $limite = 50): array
    {
        $st = $this->bd->prepare("SELECT n.id, n.asunto, n.mensaje, u.correo, p.nombres || ' ' || p.apellidos AS nombre
                                    FROM notificacion n
                                    JOIN usuario u ON u.id = n.usuario_id AND u.estado = 'HABILITADA'
                                    JOIN persona p ON p.id = u.persona_id
                                   WHERE n.canal = 'CORREO' AND n.fecha_envio IS NULL
                                   ORDER BY n.id LIMIT " . (int) $limite);
        $st->execute();
        return $st->fetchAll();
    }

    public function marcarEnviada(int $id): void
    {
        $this->bd->prepare('UPDATE notificacion SET fecha_envio = now() WHERE id = :id')->execute(['id' => $id]);
    }

    /** CU24: citas activas de una fecha cuyo paciente (o su tutor) tiene cuenta y aún no recibió recordatorio. */
    public function listarCitasSinRecordatorio(string $fecha): array
    {
        $st = $this->bd->prepare("SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.estado, c.medico, c.especialidad,
                                         c.pago_estado, c.paciente,
                                         COALESCE(u.id, ut.id) AS usuario_id
                                    FROM vw_citas_detalle c
                                    JOIN persona pp ON pp.id = c.paciente_id
                                    LEFT JOIN persona pt ON pt.id = pp.tutor_id
                                    LEFT JOIN usuario u ON u.persona_id = c.paciente_id AND u.estado = 'HABILITADA'
                                    LEFT JOIN usuario ut ON ut.persona_id = pp.tutor_id AND ut.estado = 'HABILITADA'
                                   WHERE c.fecha = CAST(:f AS date) AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                                     AND COALESCE(u.id, ut.id) IS NOT NULL
                                     AND NOT EXISTS (SELECT 1 FROM notificacion n
                                                       WHERE n.cita_id = c.id AND n.tipo = 'RECORDATORIO')
                                    ORDER BY c.hora_inicio");
        $st->execute(['f' => $fecha]);
        return $st->fetchAll();
    }

    /** CU24 (vista del personal): últimos avisos generados con su destinatario. */
    public function listarRecientes(int $limite = 50): array
    {
        $st = $this->bd->prepare("SELECT n.id, n.tipo, n.asunto, n.leida, n.fecha_creacion, n.fecha_envio,
                                         p.nombres || ' ' || p.apellidos AS destinatario, u.correo
                                    FROM notificacion n
                                    JOIN usuario u ON u.id = n.usuario_id
                                    JOIN persona p ON p.id = u.persona_id
                                   ORDER BY n.id DESC LIMIT " . (int) $limite);
        $st->execute();
        return $st->fetchAll();
    }

    public function contarSinEnviar(): int
    {
        return (int) $this->bd->query("SELECT count(*) FROM notificacion WHERE canal = 'CORREO' AND fecha_envio IS NULL")->fetchColumn();
    }
}