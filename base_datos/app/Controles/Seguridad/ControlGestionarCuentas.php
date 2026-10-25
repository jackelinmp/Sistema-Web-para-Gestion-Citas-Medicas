<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\Persona;
use App\Entidades\Rol;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarCuentas — CU02 Gestionar Cuentas de Usuario.   Responsable: Erika
 * Interfaces: IUCuentas, IUFormCuenta.  Entidades: Usuario, Persona, Rol, Bitacora.
 * Permisos: módulo SEGURIDAD (C consultar, R registrar, M modificar, A habilitar/suspender).
 */
final class ControlGestionarCuentas extends ControlBase
{
    private const MODULO = 'SEGURIDAD';

    public function listarCuentas(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $filtros = ['criterio' => trim($_GET['criterio'] ?? ''), 'rol_id' => (int) ($_GET['rol_id'] ?? 0) ?: null,
                    'estado' => $_GET['estado'] ?? ''];
        $this->vista('Seguridad/IUCuentas', [
            'cuentas' => (new Usuario())->listarCuentas($filtros['criterio'], $filtros['rol_id'], $filtros['estado']),
            'roles'   => (new Rol())->listarRoles(),
            'filtros' => $filtros,
        ]);
    }

    public function mostrarFormulario(): void
    {
        $id = (int) ($_GET['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $cuenta = $id ? (new Usuario())->obtenerPorId($id) : null;
        if ($id && !$cuenta) {
            $this->redirigir('cuentas');
        }
        $this->vista('Seguridad/IUFormCuenta', ['cuenta' => $cuenta, 'roles' => (new Rol())->listarRoles(), 'errores' => []]);
    }

    public function guardarCuenta(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $usuarios = new Usuario();
        $personas = new Persona();
        $actual = $id ? $usuarios->obtenerPorId($id) : null;
        if ($id && !$actual) {
            $this->redirigir('cuentas');
        }
        $d = $this->leerFormulario();
        $errores = $this->validar($d, $actual, $usuarios, $personas);
        if ($errores) {
            $this->vista('Seguridad/IUFormCuenta', ['cuenta' => $d + ['id' => $id], 'roles' => (new Rol())->listarRoles(),
                                                    'errores' => $errores]);
            return;
        }
        Conexion::transaccion(function () use ($d, $id, $actual, $usuarios, $personas) {
            if ($id) {
                $personas->actualizar((int) $actual['persona_id'], $d);
                $usuarios->actualizar($id, (int) $d['rol_id'], $d['correo']);
                $accion = 'MODIFICAR_USUARIO';
            } else {
                $personaId = $personas->insertar($d);
                $id = $usuarios->insertar($personaId, (int) $d['rol_id'], $d['correo'], $d['contrasena']);
                $accion = 'CREAR_USUARIO';
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), $accion, 'Cuenta: ' . $d['correo'], $this->ip(), 'usuario', $id);
        });
        Sesion::flash('ok', $actual ? 'Cuenta modificada correctamente.' : 'Cuenta registrada correctamente.');
        $this->redirigir('cuentas');
    }

    /** Subflujo habilitar / suspender (no se elimina la cuenta). */
    public function cambiarEstado(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'A');
        $id = (int) ($_POST['id'] ?? 0);
        $usuarios = new Usuario();
        $cuenta = $usuarios->obtenerPorId($id);
        if (!$cuenta) {
            $this->redirigir('cuentas');
        }
        if ($id === $this->idUsuario()) {
            Sesion::flash('error', 'No puede suspender su propia cuenta');
            $this->redirigir('cuentas');
        }
        $nuevo = $cuenta['estado'] === 'HABILITADA' ? 'SUSPENDIDA' : 'HABILITADA';
        Conexion::transaccion(function () use ($usuarios, $id, $nuevo, $cuenta) {
            $usuarios->cambiarEstado($id, $nuevo);
            (new Bitacora())->registrarEvento($this->idUsuario(), $nuevo === 'SUSPENDIDA' ? 'SUSPENDER_USUARIO' : 'HABILITAR_USUARIO',
                                              'Cuenta: ' . $cuenta['correo'], $this->ip(), 'usuario', $id);
        });
        Sesion::flash('ok', $nuevo === 'SUSPENDIDA' ? 'Cuenta suspendida.' : 'Cuenta habilitada.');
        $this->redirigir('cuentas');
    }

    private function leerFormulario(): array
    {
        $d = [];
        foreach (Persona::CAMPOS as $c) {
            $d[$c] = $this->texto($c);
        }
        $d['telefono'] = $d['telefono'] ?: null;
        $d['direccion'] = $d['direccion'] ?: null;
        $d['correo'] = strtolower($this->texto('correo'));
        $d['rol_id'] = $this->texto('rol_id');
        $d['contrasena'] = (string) ($_POST['contrasena'] ?? '');
        return $d;
    }

    private function validar(array $d, ?array $actual, Usuario $usuarios, Persona $personas): array
    {
        $e = [];
        foreach (['ci' => 'documento', 'nombres' => 'nombres', 'apellidos' => 'apellidos',
                  'fecha_nacimiento' => 'fecha de nacimiento', 'rol_id' => 'rol'] as $c => $t) {
            if ($d[$c] === '') {
                $e[$c] = 'Complete el campo ' . $t . '.';
            }
        }
        if (!in_array($d['sexo'], ['F', 'M'], true)) {
            $e['sexo'] = 'Seleccione el sexo.';
        }
        if ($d['fecha_nacimiento'] !== '' && $d['fecha_nacimiento'] > date('Y-m-d')) {
            $e['fecha_nacimiento'] = 'La fecha de nacimiento no puede ser futura.';
        }
        if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $e['correo'] = 'Ingrese un correo válido.';
        }
        $msgDuplicado = 'Ya existe una cuenta con ese correo o documento';
        if (!isset($e['correo']) && $usuarios->existeCorreo($d['correo'], $actual['id'] ?? null)) {
            $e['correo'] = $msgDuplicado;
        }
        if ($d['ci'] !== '' && $personas->existeCi($d['ci'], isset($actual['persona_id']) ? (int) $actual['persona_id'] : null)) {
            $e['ci'] = $msgDuplicado;
        }
        if (!$actual && strlen($d['contrasena']) < 8) {
            $e['contrasena'] = 'La contraseña inicial debe tener al menos 8 caracteres.';
        }
        return $e;
    }
}
