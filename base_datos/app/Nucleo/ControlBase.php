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
        return Red::ip();
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
