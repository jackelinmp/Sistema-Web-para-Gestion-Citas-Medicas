<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Conversacion — tablas conversacion y mensaje_chat (composición 1..*). CU25 Asistente virtual. */
final class Conversacion extends EntidadBase
{
    /** Abre una conversación; $pacienteId es null cuando escribe un visitante sin sesión. */
    public function iniciar(?int $pacienteId, ?string $ip): string
    {
        $st = $this->bd->prepare('INSERT INTO conversacion (paciente_id, ip) VALUES (:p, CAST(:ip AS inet)) RETURNING id');
        $st->execute(['p' => $pacienteId, 'ip' => $ip]);
        return (string) $st->fetchColumn();
    }

    public function estaActiva(string $id): bool
    {
        $st = $this->bd->prepare("SELECT 1 FROM conversacion WHERE id = CAST(:id AS uuid) AND estado = 'ACTIVA'");
        $st->execute(['id' => $id]);
        return (bool) $st->fetchColumn();
    }

    public function registrarMensaje(string $conversacionId, string $emisor, string $texto, ?string $intencion): void
    {
        $this->bd->prepare('INSERT INTO mensaje_chat (conversacion_id, emisor, texto, intencion)
                            VALUES (CAST(:c AS uuid), :e, :t, :i)')
                 ->execute(['c' => $conversacionId, 'e' => $emisor, 't' => $texto, 'i' => $intencion]);
    }

    public function listarMensajes(string $conversacionId): array
    {
        $st = $this->bd->prepare('SELECT emisor, texto, intencion, fecha_hora FROM mensaje_chat
                                   WHERE conversacion_id = CAST(:c AS uuid) ORDER BY id');
        $st->execute(['c' => $conversacionId]);
        return $st->fetchAll();
    }

    public function cerrar(string $id): void
    {
        $this->bd->prepare("UPDATE conversacion SET estado = 'CERRADA' WHERE id = CAST(:id AS uuid) AND estado = 'ACTIVA'")
                 ->execute(['id' => $id]);
    }

    /** CU26: lo que los pacientes preguntaron y el asistente no supo responder (últimos 30 días). */
    public function listarSinRespuesta(int $limite = 20): array
    {
        $st = $this->bd->prepare("SELECT lower(texto) AS texto, count(*) AS veces, max(fecha_hora) AS ultima_vez
                                    FROM mensaje_chat
                                   WHERE emisor = 'PACIENTE' AND intencion = 'SIN_RESPUESTA'
                                     AND fecha_hora > now() - interval '30 days'
                                   GROUP BY lower(texto)
                                   ORDER BY veces DESC, ultima_vez DESC LIMIT " . (int) $limite);
        $st->execute();
        return $st->fetchAll();
    }
}
