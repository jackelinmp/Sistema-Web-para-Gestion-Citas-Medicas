<?php
namespace App\Nucleo;

/** Datos de red de la petición. La IP la obtiene el servidor, nunca el usuario (RN14). */
final class Red
{
    public static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        // En la nube (Vercel) la petición llega por un proxy: la IP real es la primera de X-Forwarded-For
        if ((getenv('VERCEL') || Config::obtener('TRUST_PROXY') === 'true') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    /** URL base del sistema (termina en «/»): APP_URL o la deducida de la petición. */
    public static function urlBase(): string
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
