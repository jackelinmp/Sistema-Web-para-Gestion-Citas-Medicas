<?php
/**
 * Controlador frontal: TODA petición entra por aquí (?r=modulo/accion).
 * Solo la carpeta public/ es visible desde el navegador.
 */
declare(strict_types=1);

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
