<?php
namespace App\Nucleo;

/** Muestra una interfaz «boundary» (app/Interfaces/...) dentro de una plantilla. */
final class Vista
{
    public static function mostrar(string $interfaz, array $datos = [], ?string $plantilla = 'principal'): void
    {
        extract($datos, EXTR_SKIP);
        ob_start();
        require RAIZ . '/app/Interfaces/' . $interfaz . '.php';
        $contenido = ob_get_clean();
        if ($plantilla === null) {
            echo $contenido;
            return;
        }
        require RAIZ . '/app/Interfaces/plantillas/' . $plantilla . '.php';
    }
}
