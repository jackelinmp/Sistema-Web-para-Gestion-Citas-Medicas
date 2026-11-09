<?php
/**
 * Controlador frontal: TODA petición entra por aquí (?r=modulo/accion).
 * Solo la carpeta public/ es visible desde el navegador.
 */
declare(strict_types=1);

// Servidor interno de PHP (php -S ... public/principal.php): los archivos reales (css, js, imágenes) se sirven tal cual
if (PHP_SAPI === 'cli-server') {
    $archivo = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($archivo)) {
        return false;
    }
}

define('RAIZ', dirname(__DIR__));
require RAIZ . '/app/Nucleo/autoload.php';
require RAIZ . '/app/Nucleo/funciones.php';

use App\Nucleo\Config;
use App\Nucleo\Enrutador;
use App\Nucleo\Sesion;

Config::cargar(RAIZ . '/.env');
date_default_timezone_set('America/La_Paz');
$depurar = Config::obtener('APP_DEBUG') === 'true';
ini_set('display_errors', '0');   // los errores se muestran con la página de abajo, no a medias
// Si algo falla, nunca una página en blanco: aviso al usuario y el detalle en el registro (y en pantalla con APP_DEBUG=true).
$mostrarFalla = function (string $detalle) use ($depurar): void {
    error_log('Clínica: ' . $detalle);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><div style="font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px">'
       . '<h2>No se pudo abrir esta página</h2><p>Intente de nuevo en unos minutos. Si continúa, avise al administrador.</p>'
       . ($depurar ? '<pre style="white-space:pre-wrap;background:#f4f4f4;padding:12px;border-radius:8px">' . htmlspecialchars($detalle) . '</pre>' : '')
       . '</div>';
};
set_exception_handler(fn(\Throwable $e) => $mostrarFalla(get_class($e) . ': ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine()));
register_shutdown_function(function () use ($mostrarFalla): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $mostrarFalla($e['message'] . ' en ' . $e['file'] . ':' . $e['line']);
    }
});
Sesion::iniciar();

$ruta = $_GET['r'] ?? (Sesion::usuario() ? 'panel' : 'sesion/formulario');
(new Enrutador(require RAIZ . '/app/rutas.php'))->despachar($ruta, $_SERVER['REQUEST_METHOD']);
