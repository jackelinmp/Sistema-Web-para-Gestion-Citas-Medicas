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
if (Config::obtener('APP_DEBUG') !== 'true') {
    ini_set('display_errors', '0');
}
Sesion::iniciar();

$ruta = $_GET['r'] ?? (Sesion::usuario() ? 'panel' : 'sesion/formulario');
(new Enrutador(require RAIZ . '/app/rutas.php'))->despachar($ruta, $_SERVER['REQUEST_METHOD']);
