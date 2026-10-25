<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» TokenRecuperacion — tabla token_recuperacion (CU01, recuperar contraseña).
 * El enlace lleva un token aleatorio; en la base solo se guarda su hash SHA-256.
 */
final class TokenRecuperacion extends EntidadBase
{
    /** Crea un token nuevo (anula los anteriores del usuario) y devuelve el token en claro para el enlace. */
    public function generar(int $usuario_id, int $minutosValidez = 30): string
    {
        $token = bin2hex(random_bytes(32));
        $this->bd->prepare('UPDATE token_recuperacion SET usado = TRUE WHERE usuario_id = :u AND NOT usado')
                 ->execute(['u' => $usuario_id]);
        $this->bd->prepare('INSERT INTO token_recuperacion (usuario_id, token_hash, fecha_expiracion)
                            VALUES (:u, :h, now() + make_interval(mins => :m))')
                 ->execute(['u' => $usuario_id, 'h' => hash('sha256', $token), 'm' => $minutosValidez]);
        return $token;
    }

    /** Token sin usar y sin vencer, con el correo y nombre de su usuario. */
    public function buscarVigente(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $st = $this->bd->prepare("SELECT t.id, t.usuario_id, u.correo, p.nombres || ' ' || p.apellidos AS nombre
                                    FROM token_recuperacion t
                                    JOIN usuario u ON u.id = t.usuario_id
                                    JOIN persona p ON p.id = u.persona_id
                                   WHERE t.token_hash = :h AND NOT t.usado AND t.fecha_expiracion > now()");
        $st->execute(['h' => hash('sha256', $token)]);
        return $st->fetch() ?: null;
    }

    public function marcarUsado(int $id): void
    {
        $this->bd->prepare('UPDATE token_recuperacion SET usado = TRUE WHERE id = :id')->execute(['id' => $id]);
    }
}
