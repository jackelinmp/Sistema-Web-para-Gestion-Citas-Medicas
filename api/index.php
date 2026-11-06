<?php
/**
 * Entrada para Vercel (runtime vercel-php): reenvía cada petición al controlador frontal
 * de siempre, public/index.php. En XAMPP este archivo no se usa.
 */
chdir(__DIR__ . '/../public');
require __DIR__ . '/../public/inicio.php';
