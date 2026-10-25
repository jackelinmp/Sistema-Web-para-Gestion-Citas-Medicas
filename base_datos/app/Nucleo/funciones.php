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

/**
 * Ícono SVG en línea (hereda el color del texto). Nombres: inicio, llave, usuarios, libro, paciente,
 * corazon, medico, reloj, calendario, calendario-ok, candado, flecha, salir, tarjeta, frasco, chat,
 * grafico, ojo, escudo, menu, cerrar, modulos.
 */
function icono(string $nombre, int $tam = 20): string
{
    static $trazos = [
        'inicio'        => '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'llave'         => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2M16 7l3 3"/>',
        'usuarios'      => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2M17 3.1a4 4 0 0 1 0 7.8M22 21v-2a4 4 0 0 0-3-3.9"/>',
        'libro'         => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16M9 8h6M9 12h6"/>',
        'paciente'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'corazon'       => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
        'medico'        => '<path d="M6 3v6a6 6 0 0 0 12 0V3M4 3h4M16 3h4M12 15v2a4 4 0 0 0 8 0v-2"/><circle cx="20" cy="12" r="2"/>',
        'reloj'         => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'calendario'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'calendario-ok' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
        'candado'       => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'flecha'        => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'salir'         => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'tarjeta'       => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'frasco'        => '<path d="M9 3h6M10 3v6L4 19a2 2 0 0 0 1.7 3h12.6A2 2 0 0 0 20 19l-6-10V3"/>',
        'chat'          => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'grafico'       => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
        'ojo'           => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'escudo'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'menu'          => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'cerrar'        => '<path d="M6 6l12 12M18 6L6 18"/>',
        'modulos'       => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    ];
    return '<svg class="icono" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($trazos[$nombre] ?? $trazos['modulos']) . '</svg>';
}

/** Etiqueta del caso de uso (CU01, CU05...) o su texto alterno (Auditoría, Ciclo 2). */
function etiquetaCu(array $item): string
{
    $texto = $item['cu'] ?? ($item['etiqueta'] ?? '');
    return $texto === '' ? '' : '<span class="etiqueta-cu">' . e($texto) . '</span>';
}
