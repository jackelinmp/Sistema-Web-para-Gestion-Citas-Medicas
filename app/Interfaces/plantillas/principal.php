<?php
/** Plantilla del panel (usuario con sesión). Recibe $contenido desde Vista::mostrar(). */
use App\Nucleo\Sesion;
$usuario = Sesion::usuario();
$menu = require RAIZ . '/config/menu.php';
$rutaActual = $_GET['r'] ?? 'panel';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clínica Vida Salud</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="barra">
  <span class="marca">Clínica Vida Salud</span>
  <?php if ($usuario): ?>
    <span class="usuario"><?= e($usuario['nombre']) ?> · <?= e($usuario['rol']) ?></span>
    <form method="post" action="?r=sesion/cerrar"><?= campoCsrf() ?><button class="btn-enlace">Cerrar sesión</button></form>
  <?php endif; ?>
</header>
<div class="cuerpo">
  <?php if ($usuario): ?>
  <nav class="menu">
    <?php
    // Agrupa las opciones visibles; un grupo sin opciones visibles no se muestra.
    $grupoPendiente = null;
    foreach ($menu as $op):
      if (isset($op['grupo'])) { $grupoPendiente = $op['grupo']; continue; }
      if (empty($op['listo'])) continue;
      if ($op['modulo'] && !Sesion::puede($op['modulo'], $op['accion'])) continue;
      if ($grupoPendiente !== null): ?>
        <div class="grupo"><?= e($grupoPendiente) ?></div>
        <?php $grupoPendiente = null;
      endif; ?>
      <a href="?r=<?= e($op['ruta']) ?>" class="<?= str_starts_with($rutaActual, $op['ruta']) ? 'activo' : '' ?>"><?= e($op['texto']) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <main class="contenido">
    <?php require __DIR__ . '/mensajes.php'; ?>
    <?= $contenido ?>
  </main>
</div>
<script src="js/app.js"></script>
</body>
</html>
