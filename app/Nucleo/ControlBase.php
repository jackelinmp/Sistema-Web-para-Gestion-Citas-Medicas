<?php
namespace App\Nucleo;

/** Funciones comunes de todos los controles «control». */
abstract class ControlBase
{
    protected function vista(string $interfaz, array $datos = [], ?string $plantilla = 'principal'): void
    {
        Vista::mostrar($interfaz, $datos, $plantilla);
    }

    protected function redirigir(string $ruta): never
    {
        header('Location: ?r=' . $ruta);
        exit;
    }

    /** IP de origen obtenida por el servidor, nunca enviada por el usuario (RN14). */
    protected function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        // En la nube (Vercel) la petición llega por un proxy: la IP real es la primera de X-Forwarded-For
        if ((getenv('VERCEL') || Config::obtener('TRUST_PROXY') === 'true') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function idUsuario(): ?int
    {
        return isset(Sesion::usuario()['id']) ? (int) Sesion::usuario()['id'] : null;
    }

    protected function texto(string $campo): string
    {
        return trim((string) ($_POST[$campo] ?? ''));
    }
}
