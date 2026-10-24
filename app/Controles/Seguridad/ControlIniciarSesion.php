<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\Usuario;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlIniciarSesion — CU01 Iniciar Sesión.
 * Interfaces: IUIniciarSesion, IUPanel.  Entidades: Usuario, Rol, Bitacora.
 */
final class ControlIniciarSesion extends ControlBase
{
    public function mostrarFormulario(): void
    {
        if (Sesion::usuario()) {
            $this->redirigir('panel');
        }
        $this->vista('Seguridad/IUIniciarSesion', ['correo' => ''], 'publica');
    }

    public function iniciarSesion(): void
    {
        $correo = $this->texto('correo');
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        if ($correo === '' || $contrasena === '') {
            Sesion::flash('error', 'Ingrese su correo y su contraseña.');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }
        $usuarios = new Usuario();
        $bitacora = new Bitacora();
        $cuenta = $usuarios->buscarPorCorreo($correo);

        if (!$cuenta || !password_verify($contrasena, $cuenta['contrasena_hash'])) {
            if ($cuenta) {
                $usuarios->registrarIntentoFallido((int) $cuenta['id']);
            }
            $bitacora->registrarEvento($cuenta ? (int) $cuenta['id'] : null, 'INICIO_SESION_FALLIDO',
                                       'Intento fallido para ' . $correo, $this->ip(), 'usuario', $cuenta['id'] ?? null);
            Sesion::flash('error', 'Correo o contraseña incorrectos');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }
        if ($cuenta['estado'] !== 'HABILITADA') {
            Sesion::flash('error', 'Cuenta suspendida. Contacte al administrador');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }
        $usuarios->registrarAcceso((int) $cuenta['id']);
        unset($cuenta['contrasena_hash']);
        Sesion::abrir($cuenta);
        $bitacora->registrarEvento((int) $cuenta['id'], 'INICIO_SESION', 'Ingreso al sistema', $this->ip(), 'usuario', (int) $cuenta['id']);
        $this->redirigir('panel');
    }

    public function cerrarSesion(): void
    {
        if ($id = $this->idUsuario()) {
            (new Bitacora())->registrarEvento($id, 'CIERRE_SESION', 'Salida del sistema', $this->ip(), 'usuario', $id);
        }
        Sesion::cerrar();
        Sesion::iniciar();
        Sesion::flash('ok', 'Sesión cerrada correctamente.');
        $this->redirigir('sesion/formulario');
    }

    public function mostrarPanel(): void
    {
        Sesion::exigirLogin();
        $this->vista('Seguridad/IUPanel');
    }
}
