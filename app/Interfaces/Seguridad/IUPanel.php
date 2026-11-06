<?php /** «boundary» IUPanel — panel inicial según el rol (CU01, paso 5). */
use App\Nucleo\Sesion; ?>
<h2>Bienvenido(a), <?= e(Sesion::usuario()['nombre']) ?></h2>
<p>Seleccione una opción del menú.</p>
