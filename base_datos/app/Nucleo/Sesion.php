<?php
namespace App\Nucleo;

use App\Entidades\Rol;

/** Sesión del usuario, permisos RBAC (RF02, RNF05), mensajes flash y token CSRF. */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (Config::obtener('SESSION_DRIVER') === 'cookie') {
                // En la nube (Vercel) no hay archivos persistentes: la sesión viaja cifrada en una cookie.
                // La salida se guarda en un búfer y la sesión se escribe antes de enviarla.
                ob_start();
                session_set_save_handler(new SesionCookie((string) Config::obtener('APP_KEY')), false);
                register_shutdown_function('session_write_close');
            }
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => self::esHttps()]);
            session_start();
        }
    }

    public static function esHttps(): bool
    {
        return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function abrir(array $usuario): void
    {
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;     // id, persona_id, rol_id, rol, nombre
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function exigirLogin(): void
    {
        if (!self::usuario()) {
            self::flash('error', 'Inicie sesión para continuar.');
            header('Location: ?r=sesion/formulario');
            exit;
        }
    }

    /** $accion: C consultar, R registrar, M modificar, A anular (matriz RBAC de 4.1.1). */
    public static function puede(string $modulo, string $accion): bool
    {
        $u = self::usuario();
        return $u !== null && (new Rol())->tienePermiso((int) $u['rol_id'], $modulo, $accion);
    }

    public static function exigirPermiso(string $modulo, string $accion): void
    {
        self::exigirLogin();
        if (!self::puede($modulo, $accion)) {
            self::denegar("Intento de acceso a $modulo/$accion (" . ($_GET['r'] ?? '') . ')');
        }
    }

    /** CU03: muestra «Acceso no autorizado» (403) y deja el intento en la bitácora. No vuelve. */
    public static function denegar(string $detalle): never
    {
        try {
            (new \App\Entidades\Bitacora())->registrarEvento((int) (self::usuario()['id'] ?? 0) ?: null,
                'ACCESO_NO_AUTORIZADO', $detalle, Red::ip());
        } catch (\Throwable $e) {
            // la bitácora nunca debe impedir mostrar el rechazo
        }
        http_response_code(403);
        Vista::mostrar('plantillas/IUAccesoDenegado', [], 'principal');
        exit;
    }

    public static function flash(string $tipo, ?string $mensaje = null): ?string
    {
        if ($mensaje !== null) {
            $_SESSION['flash'][$tipo] = $mensaje;
            return null;
        }
        $m = $_SESSION['flash'][$tipo] ?? null;
        unset($_SESSION['flash'][$tipo]);
        return $m;
    }

    public static function tokenCsrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verificarCsrf(): void
    {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            http_response_code(419);
            exit('La sesión del formulario expiró. Vuelva a intentarlo.');
        }
    }
}
