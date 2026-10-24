<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Usuario — tabla usuario. */
final class Usuario extends EntidadBase
{
    /** Datos de la cuenta, su rol y el nombre de la persona (CU01). */
    public function buscarPorCorreo(string $correo): ?array
    {
        $sql = 'SELECT u.id, u.persona_id, u.rol_id, u.correo, u.contrasena_hash, u.estado,
                       r.nombre AS rol, p.nombres || \' \' || p.apellidos AS nombre
                  FROM usuario u
                  JOIN rol r     ON r.id = u.rol_id
                  JOIN persona p ON p.id = u.persona_id
                 WHERE lower(u.correo) = lower(:correo)';
        $st = $this->bd->prepare($sql);
        $st->execute(['correo' => $correo]);
        return $st->fetch() ?: null;
    }

    public function registrarIntentoFallido(int $id): void
    {
        $this->bd->prepare('UPDATE usuario SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id')
                 ->execute(['id' => $id]);
    }

    public function registrarAcceso(int $id): void
    {
        $this->bd->prepare('UPDATE usuario SET intentos_fallidos = 0, ultimo_acceso = now() WHERE id = :id')
                 ->execute(['id' => $id]);
    }

    /** CU02: lista de cuentas con nombre, correo, rol y estado; filtros por texto, rol y estado. */
    public function listarCuentas(string $criterio = '', ?int $rol_id = null, string $estado = ''): array
    {
        $like = '%' . $criterio . '%';
        $st = $this->bd->prepare("SELECT u.id, u.persona_id, u.correo, u.estado, u.ultimo_acceso, u.rol_id, r.nombre AS rol,
                                         p.ci, p.nombres || ' ' || p.apellidos AS nombre
                                    FROM usuario u
                                    JOIN rol r     ON r.id = u.rol_id
                                    JOIN persona p ON p.id = u.persona_id
                                   WHERE (CAST(:c AS text) = '' OR u.correo ILIKE :l1
                                          OR (p.nombres || ' ' || p.apellidos) ILIKE :l2 OR p.ci ILIKE :l3)
                                     AND (CAST(:r1 AS smallint) IS NULL OR u.rol_id = :r2)
                                     AND (CAST(:e1 AS text) = '' OR u.estado = :e2)
                                   ORDER BY p.apellidos, p.nombres");
        $st->execute(['c' => $criterio, 'l1' => $like, 'l2' => $like, 'l3' => $like,
                      'r1' => $rol_id, 'r2' => $rol_id, 'e1' => $estado, 'e2' => $estado]);
        return $st->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT u.id, u.persona_id, u.rol_id, u.correo, u.estado,
                                         p.ci, p.nombres, p.apellidos, p.fecha_nacimiento, p.sexo, p.telefono, p.direccion
                                    FROM usuario u JOIN persona p ON p.id = u.persona_id
                                   WHERE u.id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function existeCorreo(string $correo, ?int $excluir_id = null): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM usuario WHERE lower(correo) = lower(:correo) AND id IS DISTINCT FROM :excluir');
        $st->execute(['correo' => $correo, 'excluir' => $excluir_id]);
        return (bool) $st->fetchColumn();
    }

    /** Crea la cuenta con la contraseña cifrada (password_hash, RNF04). */
    public function insertar(int $persona_id, int $rol_id, string $correo, string $contrasena, bool $debe_cambiar = true): int
    {
        $st = $this->bd->prepare('INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, debe_cambiar_contrasena)
                                  VALUES (:persona, :rol, :correo, :hash, :cambiar) RETURNING id');
        $st->execute(['persona' => $persona_id, 'rol' => $rol_id, 'correo' => $correo,
                      'hash' => password_hash($contrasena, PASSWORD_DEFAULT), 'cambiar' => $debe_cambiar ? 'true' : 'false']);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $rol_id, string $correo): void
    {
        $this->bd->prepare('UPDATE usuario SET rol_id = :rol, correo = :correo WHERE id = :id')
                 ->execute(['rol' => $rol_id, 'correo' => $correo, 'id' => $id]);
    }

    /** HABILITADA o SUSPENDIDA (no se elimina la cuenta). */
    public function cambiarEstado(int $id, string $estado): void
    {
        $this->bd->prepare('UPDATE usuario SET estado = :estado WHERE id = :id')
                 ->execute(['estado' => $estado, 'id' => $id]);
    }
}
