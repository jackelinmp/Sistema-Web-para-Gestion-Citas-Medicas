<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Rol — tablas rol, rol_permiso y permiso (RBAC). */
final class Rol extends EntidadBase
{
    /** Permisos ya cargados por rol: una sola consulta por petición, no una por cada pregunta del menú. */
    private static array $cache = [];

    public function tienePermiso(int $rolId, string $modulo, string $accion): bool
    {
        if (!isset(self::$cache[$rolId])) {
            $st = $this->bd->prepare('SELECT p.modulo || \'|\' || p.accion
                                        FROM rol_permiso rp
                                        JOIN permiso p ON p.id = rp.permiso_id
                                        JOIN rol r     ON r.id = rp.rol_id
                                       WHERE rp.rol_id = :rol AND r.estado');
            $st->execute(['rol' => $rolId]);
            self::$cache[$rolId] = array_flip($st->fetchAll(\PDO::FETCH_COLUMN));
        }
        return isset(self::$cache[$rolId]["$modulo|$accion"]);
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

    // ---- CU03 Gestionar Roles y Permisos ----

    /** Todos los roles con su cantidad de usuarios. */
    public function listarConUsuarios(): array
    {
        return $this->bd->query('SELECT r.id, r.nombre, r.estado, count(u.id) AS usuarios
                                   FROM rol r LEFT JOIN usuario u ON u.rol_id = r.id
                                  GROUP BY r.id ORDER BY r.id')->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, nombre, estado FROM rol WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /** Catálogo de permisos (módulo × acción) con la marca de si el rol lo tiene. */
    public function matriz(int $rolId): array
    {
        $st = $this->bd->prepare('SELECT p.id, p.modulo, p.accion, (rp.rol_id IS NOT NULL) AS tiene
                                    FROM permiso p
                                    LEFT JOIN rol_permiso rp ON rp.permiso_id = p.id AND rp.rol_id = :rol
                                   ORDER BY p.modulo, p.accion');
        $st->execute(['rol' => $rolId]);
        $m = [];
        foreach ($st->fetchAll() as $f) {
            $m[$f['modulo']][$f['accion']] = ['id' => (int) $f['id'], 'tiene' => (bool) $f['tiene']];
        }
        return $m;
    }

    /** Ids de los permisos de un módulo (para proteger SEGURIDAD del administrador). */
    public function idsPermisosModulo(string $modulo): array
    {
        $st = $this->bd->prepare('SELECT id FROM permiso WHERE modulo = :m');
        $st->execute(['m' => $modulo]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Reemplaza los permisos del rol en una sola transacción. Devuelve [agregados, quitados]. */
    public function guardarPermisos(int $rolId, array $permisoIds): array
    {
        $this->bd->beginTransaction();
        try {
            $st = $this->bd->prepare('SELECT permiso_id FROM rol_permiso WHERE rol_id = :r');
            $st->execute(['r' => $rolId]);
            $antes = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
            $validos = array_map('intval', $this->bd->query('SELECT id FROM permiso')->fetchAll(\PDO::FETCH_COLUMN));
            $nuevos = array_values(array_intersect(array_unique(array_map('intval', $permisoIds)), $validos));
            $this->bd->prepare('DELETE FROM rol_permiso WHERE rol_id = :r')->execute(['r' => $rolId]);
            $ins = $this->bd->prepare('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (:r, :p)');
            foreach ($nuevos as $p) {
                $ins->execute(['r' => $rolId, 'p' => $p]);
            }
            $this->bd->commit();
        } catch (\Throwable $e) {
            $this->bd->rollBack();
            throw $e;
        }
        return [count(array_diff($nuevos, $antes)), count(array_diff($antes, $nuevos))];
    }
}
