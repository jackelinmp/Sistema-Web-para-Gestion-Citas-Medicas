<?php
/**
 * Funciones de apoyo disponibles en todas las interfaces.
 * Usarlas SIEMPRE para mostrar datos: así todas las pantallas muestran
 * fechas, montos y estados con el mismo formato (docs/guia_interfaz.md).
 */

/** Escapa texto para HTML (evita XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Campo oculto con el token CSRF para todo formulario POST. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(\App\Nucleo\Sesion::tokenCsrf()) . '">';
}

/**
 * Insignia de color para un estado: PROGRAMADA, CONFIRMADA, ATENDIDA, CANCELADA,
 * INASISTENCIA, ACTIVO, INACTIVO, HABILITADA, SUSPENDIDA, PENDIENTE, PAGADO, ...
 * Acepta también booleanos (true = Activo, false = Inactivo).
 * Ejemplo: <?= insignia($cita['estado']) ?>
 */
function insignia(string|bool|null $estado): string
{
    if (is_bool($estado)) {
        $estado = $estado ? 'ACTIVO' : 'INACTIVO';
    }
    $clave = strtolower((string) $estado);
    $texto = ucfirst(str_replace('_', ' ', $clave));
    return '<span class="insignia ' . e($clave) . '">' . e($texto) . '</span>';
}

/** Fecha en formato dd/mm/aaaa. Ejemplo: fecha('2026-11-15') -> 15/11/2026 */
function fecha(?string $valor): string
{
    return $valor ? e(date('d/m/Y', strtotime($valor))) : '';
}

/** Fecha y hora en formato dd/mm/aaaa HH:MM. */
function fechaHora(?string $valor): string
{
    return $valor ? e(date('d/m/Y H:i', strtotime($valor))) : '';
}

/** Hora en formato HH:MM. Ejemplo: hora('09:00:00') -> 09:00 */
function hora(?string $valor): string
{
    return $valor ? e(substr($valor, 0, 5)) : '';
}

/** Monto en bolivianos con dos decimales. Ejemplo: bs(1250.5) -> Bs 1.250,50 */
function bs(float|string|null $monto): string
{
    return 'Bs ' . e(number_format((float) $monto, 2, ',', '.'));
}

/** Mensaje de error bajo un campo de formulario. Ejemplo: <?= errorCampo($errores, 'nombre') ?> */
function errorCampo(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<small class="error-campo">' . e($errores[$campo]) . '</small>' : '';
}
