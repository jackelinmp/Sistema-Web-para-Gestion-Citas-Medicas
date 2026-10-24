<?php
namespace App\Controles\Seguridad;

use App\Entidades\Bitacora;
use App\Entidades\ParametroSistema;
use App\Entidades\TokenRecuperacion;
use App\Entidades\Usuario;
use App\Nucleo\Config;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;
use App\Servicios\ServicioCorreo;

/**
 * «control» ControlIniciarSesion — CU01 Iniciar Sesión.
 * Incluye: bloqueo temporal tras 3 intentos fallidos, recuperación de contraseña por correo
 * y cambio de contraseña (obligatorio en el primer ingreso). Contraseñas cifradas con bcrypt.
 * Interfaces: IUIniciarSesion, IUPanel, IURecuperarContrasena, IURestablecerContrasena, IUCambiarContrasena.
 * Entidades: Usuario, Rol, TokenRecuperacion, ParametroSistema, Bitacora.  Servicio: ServicioCorreo.
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

        // 1) Cuenta bloqueada temporalmente: no se revisa la contraseña
        if ($cuenta && ($minutos = $usuarios->estaBloqueado($cuenta)) > 0) {
            $this->mostrarBloqueo($correo, $minutos);
            return;
        }

        // 2) Contraseña incorrecta: suma un intento y bloquea al llegar al máximo
        if (!$cuenta || !password_verify($contrasena, $cuenta['contrasena_hash'])) {
            $mensaje = 'Correo o contraseña incorrectos.';
            if ($cuenta) {
                $parametros = new ParametroSistema();
                $max = (int) $parametros->obtenerValor('INTENTOS_MAX_LOGIN', '3');
                $minBloqueo = (int) $parametros->obtenerValor('MINUTOS_BLOQUEO', '15');
                $r = $usuarios->registrarIntentoFallido((int) $cuenta['id'], $max, $minBloqueo);
                if ($r['bloqueada']) {
                    $bitacora->registrarEvento((int) $cuenta['id'], 'BLOQUEO_CUENTA',
                        "Cuenta bloqueada $minBloqueo minutos por $max intentos fallidos", $this->ip(), 'usuario', (int) $cuenta['id']);
                    $this->mostrarBloqueo($correo, $minBloqueo);
                    return;
                }
                $quedan = $max - $r['intentos'];
                $mensaje .= ' Le queda' . ($quedan === 1 ? ' 1 intento' : "n $quedan intentos") . ' antes del bloqueo temporal.';
            }
            $bitacora->registrarEvento($cuenta ? (int) $cuenta['id'] : null, 'INICIO_SESION_FALLIDO',
                                       'Intento fallido para ' . $correo, $this->ip(), 'usuario', $cuenta['id'] ?? null);
            Sesion::flash('error', $mensaje);
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }

        // 3) Cuenta suspendida por el administrador
        if ($cuenta['estado'] !== 'HABILITADA') {
            Sesion::flash('error', 'Cuenta suspendida. Contacte al administrador.');
            $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
            return;
        }

        // 4) Ingreso correcto
        $usuarios->registrarAcceso((int) $cuenta['id']);
        $debeCambiar = (bool) $cuenta['debe_cambiar_contrasena'];
        unset($cuenta['contrasena_hash'], $cuenta['debe_cambiar_contrasena'], $cuenta['intentos_fallidos'], $cuenta['bloqueado_hasta']);
        Sesion::abrir($cuenta);
        $bitacora->registrarEvento((int) $cuenta['id'], 'INICIO_SESION', 'Ingreso al sistema', $this->ip(), 'usuario', (int) $cuenta['id']);
        if ($debeCambiar) {
            Sesion::flash('aviso', 'Por seguridad, cambie la contraseña inicial antes de continuar.');
            $this->redirigir('sesion/cambiar');
        }
        $this->redirigir('panel');
    }

    /** Muestra en la pantalla de inicio de sesión el aviso de cuenta bloqueada (RF: bloqueo visible). */
    private function mostrarBloqueo(string $correo, int $minutos): void
    {
        Sesion::flash('aviso', 'Su cuenta está bloqueada temporalmente por varios intentos fallidos. '
            . 'Podrá intentar de nuevo en ' . $minutos . ($minutos === 1 ? ' minuto' : ' minutos')
            . ', o recuperar su contraseña con el enlace de abajo.');
        $this->vista('Seguridad/IUIniciarSesion', ['correo' => $correo], 'publica');
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

    // ------------------------------------------------- recuperar contraseña por correo

    public function mostrarRecuperacion(): void
    {
        $this->vista('Seguridad/IURecuperarContrasena', ['correo' => ''], 'publica');
    }

    public function solicitarRecuperacion(): void
    {
        $correo = $this->texto('correo');
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            Sesion::flash('error', 'Ingrese un correo electrónico válido.');
            $this->vista('Seguridad/IURecuperarContrasena', ['correo' => $correo], 'publica');
            return;
        }
        $enlacePrueba = null;
        $cuenta = (new Usuario())->buscarPorCorreo($correo);
        if ($cuenta && $cuenta['estado'] === 'HABILITADA') {
            $minutos = (int) (new ParametroSistema())->obtenerValor('MINUTOS_ENLACE_RECUP', '30');
            $token = (new TokenRecuperacion())->generar((int) $cuenta['id'], $minutos);
            $enlace = $this->urlBase() . '?r=sesion/restablecer&token=' . $token;
            $enviado = (new ServicioCorreo())->enviarEnlaceRecuperacion($cuenta['correo'], $cuenta['nombre'], $enlace, $minutos);
            (new Bitacora())->registrarEvento((int) $cuenta['id'], 'SOLICITAR_RECUPERACION',
                'Enlace de recuperación ' . ($enviado ? 'enviado' : 'NO enviado (correo sin configurar o con error)'),
                $this->ip(), 'usuario', (int) $cuenta['id']);
            // Sin servidor de correo y en modo de pruebas, el enlace se muestra en pantalla
            if (!$enviado && Config::obtener('APP_DEBUG') === 'true') {
                $enlacePrueba = $enlace;
            }
        }
        // Mismo mensaje exista o no la cuenta: no se revela qué correos están registrados
        Sesion::flash('ok', 'Si el correo está registrado, le enviamos un enlace para restablecer su contraseña. Revise su bandeja de entrada y la carpeta de spam.');
        $this->vista('Seguridad/IURecuperarContrasena', ['correo' => '', 'enlacePrueba' => $enlacePrueba], 'publica');
    }

    public function mostrarRestablecer(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        if (!(new TokenRecuperacion())->buscarVigente($token)) {
            Sesion::flash('error', 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            $this->redirigir('sesion/recuperar');
        }
        $this->vista('Seguridad/IURestablecerContrasena', ['token' => $token], 'publica');
    }

    public function restablecerContrasena(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        $tokens = new TokenRecuperacion();
        $vigente = $tokens->buscarVigente($token);
        if (!$vigente) {
            Sesion::flash('error', 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            $this->redirigir('sesion/recuperar');
        }
        $contrasena = (string) ($_POST['contrasena'] ?? '');
        if ($error = self::validarContrasena($contrasena, (string) ($_POST['confirmacion'] ?? ''))) {
            Sesion::flash('error', $error);
            $this->vista('Seguridad/IURestablecerContrasena', ['token' => $token], 'publica');
            return;
        }
        (new Usuario())->cambiarContrasena((int) $vigente['usuario_id'], $contrasena);
        $tokens->marcarUsado((int) $vigente['id']);
        (new Bitacora())->registrarEvento((int) $vigente['usuario_id'], 'RESTABLECER_CONTRASENA',
            'Contraseña restablecida con enlace de recuperación', $this->ip(), 'usuario', (int) $vigente['usuario_id']);
        Sesion::flash('ok', 'Su contraseña fue actualizada. Ya puede iniciar sesión.');
        $this->redirigir('sesion/formulario');
    }

    // ------------------------------------------------- cambiar contraseña (con sesión)

    public function mostrarCambioContrasena(): void
    {
        Sesion::exigirLogin();
        $this->vista('Seguridad/IUCambiarContrasena');
    }

    public function cambiarContrasena(): void
    {
        Sesion::exigirLogin();
        $id = (int) $this->idUsuario();
        $usuarios = new Usuario();
        if (!password_verify((string) ($_POST['actual'] ?? ''), (string) $usuarios->obtenerHash($id))) {
            Sesion::flash('error', 'La contraseña actual no es correcta.');
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        $nueva = (string) ($_POST['contrasena'] ?? '');
        if ($error = self::validarContrasena($nueva, (string) ($_POST['confirmacion'] ?? ''))) {
            Sesion::flash('error', $error);
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        if (password_verify($nueva, (string) $usuarios->obtenerHash($id))) {
            Sesion::flash('error', 'La nueva contraseña debe ser distinta de la actual.');
            $this->vista('Seguridad/IUCambiarContrasena');
            return;
        }
        $usuarios->cambiarContrasena($id, $nueva);
        (new Bitacora())->registrarEvento($id, 'CAMBIAR_CONTRASENA', 'El usuario cambió su contraseña', $this->ip(), 'usuario', $id);
        Sesion::flash('ok', 'Contraseña cambiada correctamente.');
        $this->redirigir('panel');
    }

    /** Regla de contraseña: mínimo 8 caracteres, al menos una letra y un número, y confirmación igual. */
    public static function validarContrasena(string $contrasena, string $confirmacion): ?string
    {
        if (strlen($contrasena) < 8 || !preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
            return 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
        }
        if ($contrasena !== $confirmacion) {
            return 'La confirmación no coincide con la contraseña.';
        }
        return null;
    }

    /** Dirección base del sistema para el enlace del correo (APP_URL o la del navegador). */
    private function urlBase(): string
    {
        if ($url = Config::obtener('APP_URL')) {
            return rtrim($url, '/') . '/';
        }
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (str_ends_with($ruta, '.php')) {
            $ruta = dirname($ruta);
        }
        return (Sesion::esHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($ruta, '/') . '/';
    }
}
