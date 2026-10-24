<?php
/**
 * Carga automática de clases: App\Controles\Especialidades\ControlGestionarEspecialidades
 * se busca en app/Controles/Especialidades/ControlGestionarEspecialidades.php
 */
spl_autoload_register(function (string $clase): void {
    if (!str_starts_with($clase, 'App\\')) {
        return;
    }
    $archivo = RAIZ . '/app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});
