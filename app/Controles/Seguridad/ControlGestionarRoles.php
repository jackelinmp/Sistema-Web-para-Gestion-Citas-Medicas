<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\Rol;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarRoles — CU03 Gestionar Roles y Permisos (RBAC).
 * Matriz de módulos × acciones (consultar, registrar, modificar, anular) por rol.
 * Regla: el rol ADMINISTRADOR no puede perder los permisos de SEGURIDAD. Registra MODIFICAR_PERMISOS.
 * Interfaz: IURoles.
 */
final class ControlGestionarRoles extends ControlBase
{
    public function listarRoles(): void
    {
        Sesion::exigirPermiso('SEGURIDAD', 'M');
        $rol = new Rol();
        $roles = $rol->listarConUsuarios();
        $id = (int) ($_GET['rol_id'] ?? ($roles[0]['id'] ?? 0));
        $sel = $rol->obtener($id) ?? $rol->obtener((int) $roles[0]['id']);
        $this->vista('Seguridad/IURoles', ['roles' => $roles, 'seleccionado' => $sel, 'matriz' => $rol->matriz((int) $sel['id'])]);
    }

    public function guardarPermisos(): void
    {
        Sesion::exigirPermiso('SEGURIDAD', 'M');
        $rolId = (int) ($_POST['rol_id'] ?? 0);
        $rol = new Rol();
        $sel = $rol->obtener($rolId);
        if (!$sel) {
            Sesion::flash('error', 'El rol indicado no existe.');
            $this->redirigir('roles');
        }
        $ids = array_map('intval', (array) ($_POST['permiso'] ?? []));
        if ($sel['nombre'] === 'ADMINISTRADOR' && array_diff($rol->idsPermisosModulo('SEGURIDAD'), $ids)) {
            Sesion::flash('error', 'El rol Administrador no puede perder los permisos de seguridad.');
            $this->redirigir('roles&rol_id=' . $rolId);
        }
        [$agregados, $quitados] = $rol->guardarPermisos($rolId, $ids);
        (new Bitacora())->registrarEvento($this->idUsuario(), 'MODIFICAR_PERMISOS',
            "Rol {$sel['nombre']}: +$agregados / -$quitados permisos", $this->ip(), 'rol', $rolId);
        Sesion::flash('ok', "Permisos del rol {$sel['nombre']} actualizados ($agregados agregados, $quitados quitados).");
        $this->redirigir('roles&rol_id=' . $rolId);
    }
}
