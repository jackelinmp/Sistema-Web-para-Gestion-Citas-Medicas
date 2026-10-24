<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Rol — tablas rol, rol_permiso y permiso (RBAC). */
final class Rol extends EntidadBase
{
    private static array $cache = [];

    public function tienePermiso(int $rolId, string $modulo, string $accion): bool
    {
        $clave = "$rolId|$modulo|$accion";
        if (!isset(self::$cache[$clave])) {
            $st = $this->bd->prepare('SELECT 1 FROM rol_permiso rp
                                        JOIN permiso p ON p.id = rp.permiso_id
                                        JOIN rol r     ON r.id = rp.rol_id
                                       WHERE rp.rol_id = :rol AND r.estado AND p.modulo = :modulo AND p.accion = :accion');
            $st->execute(['rol' => $rolId, 'modulo' => $modulo, 'accion' => $accion]);
            self::$cache[$clave] = (bool) $st->fetchColumn();
        }
        return self::$cache[$clave];
    }

    /** CU02: roles activos para el formulario de cuentas. */
    public function listarRoles(): array
    {
        return $this->bd->query('SELECT id, nombre FROM rol WHERE estado ORDER BY id')->fetchAll();
    }

    /** Id de un rol por su nombre (ADMINISTRADOR, RECEPCIONISTA, MEDICO, PACIENTE). */
    public function obtenerIdPorNombre(string $nombre): int
    {
        $st = $this->bd->prepare('SELECT id FROM rol WHERE nombre = :nombre');
        $st->execute(['nombre' => $nombre]);
        return (int) $st->fetchColumn();
    }
}
