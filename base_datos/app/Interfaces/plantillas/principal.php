<?php
/**
 * Plantilla del panel (usuario con sesión). Recibe $contenido desde Vista::mostrar().
 * El menú sale de config/ciclos.php: las opciones sin permiso del rol, o de un ciclo aún cerrado,
 * se muestran BLOQUEADAS (con candado) en lugar de ocultarse, para que se vea qué existe.
 */
use App\Nucleo\Ciclos;
use App\Nucleo\Sesion;

$usuario = Sesion::usuario();
$rutaActual = $_GET['r'] ?? 'panel';
$ciclos = $usuario ? Ciclos::evaluar() : [];
$itemActual = $usuario ? Ciclos::itemActual($ciclos, $rutaActual) : null;
$iniciales = $usuario ? mb_strtoupper(mb_substr(preg_replace('/[^\p{L} ]/u', '', $usuario['nombre']), 0, 1)
                       . mb_substr(strstr(preg_replace('/[^\p{L} ]/u', '', $usuario['nombre']) . ' ', ' ') ?: '', 1, 1)) : '';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clínica Vida Salud</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<div class="app">
  <?php if ($usuario): ?>
  <aside class="menu" id="menu-principal" aria-label="Menú principal">
    <a class="menu-marca" href="?r=panel">
      <span class="logo"><?= icono('corazon', 22) ?></span>
      <span><strong>Vida Salud</strong><small>Gestión de citas médicas</small></span>
    </a>

    <nav class="menu-nav">
      <a href="?r=panel" class="nav <?= $rutaActual === 'panel' && ($_GET['ciclo'] ?? '') !== 'todos' ? 'activo' : '' ?>">
        <?= icono('inicio') ?><span>Inicio</span>
      </a>
      <a href="?r=panel&amp;ciclo=todos" class="nav <?= ($_GET['ciclo'] ?? '') === 'todos' ? 'activo' : '' ?>">
        <?= icono('modulos') ?><span>Todos los módulos</span>
      </a>

      <?php foreach ($ciclos as $ciclo): ?>
        <?php $cerrado = empty($ciclo['abierto']); ?>
        <details class="ciclo <?= $cerrado ? 'ciclo-cerrado' : '' ?>" <?= $cerrado ? '' : 'open' ?>>
          <summary>
            <span><?= e($ciclo['nombre']) ?></span>
            <?php if ($cerrado): ?><span class="chip-bloqueo"><?= icono('candado', 12) ?> Bloqueado</span>
            <?php else: ?><span class="chip-avance"><?= (int) $ciclo['disponibles'] ?>/<?= (int) $ciclo['total'] ?></span><?php endif; ?>
          </summary>
          <?php foreach ($ciclo['paquetes'] as $paquete): ?>
            <div class="nav-grupo"><?= e($paquete['id']) ?> · <?= e($paquete['titulo']) ?></div>
            <?php foreach ($paquete['items'] as $it):
              $activo = $itemActual && $itemActual['texto'] === $it['texto'] && $itemActual['ciclo'] === $it['ciclo'];
              if ($it['estado'] === 'ok'): ?>
                <a href="?r=<?= e($it['ruta']) ?>" class="nav <?= $activo ? 'activo' : '' ?>" <?= $activo ? 'aria-current="page"' : '' ?>>
                  <?= icono($it['icono']) ?><span><?= e($it['texto']) ?></span><?= etiquetaCu($it) ?>
                </a>
              <?php else: ?>
                <span class="nav bloqueado" aria-disabled="true" title="<?= e(Ciclos::motivo($it['estado'])) ?>">
                  <?= icono($it['icono']) ?><span><?= e($it['texto']) ?></span><?= icono('candado', 14) ?>
                </span>
              <?php endif;
            endforeach; ?>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>

    </nav>

    <div class="menu-usuario">
      <span class="avatar"><?= e($iniciales) ?></span>
      <span class="menu-usuario-datos"><strong><?= e($usuario['nombre']) ?></strong><small><?= e($usuario['rol']) ?></small></span>
      <form method="post" action="?r=sesion/cerrar"><?= campoCsrf() ?>
        <button class="btn-icono" title="Cerrar sesión" aria-label="Cerrar sesión"><?= icono('salir') ?></button>
      </form>
    </div>
  </aside>
  <div class="velo" data-cerrar-menu></div>
  <?php endif; ?>

  <div class="principal">
    <header class="barra">
      <?php if ($usuario): ?>
        <button type="button" class="btn-menu" aria-label="Abrir menú" aria-expanded="false" aria-controls="menu-principal"><?= icono('menu', 22) ?></button>
        <div class="migas">
          <?php if ($itemActual): ?>
            <span class="ciclo-chip"><?= e($ciclos[$itemActual['ciclo']]['nombre']) ?> · <?= e($itemActual['paquete']) ?></span>
            <?= etiquetaCu($itemActual) ?>
            <strong><?= e($itemActual['texto']) ?></strong>
          <?php else: ?>
            <strong>Clínica Vida Salud</strong>
          <?php endif; ?>
        </div>
        <span class="rol-chip"><?= e($usuario['rol']) ?></span>
        <a class="btn-enlace enlace-barra" href="?r=sesion/cambiar">Cambiar contraseña</a>
      <?php else: ?>
        <strong>Clínica Vida Salud</strong>
      <?php endif; ?>
    </header>

    <main class="contenido">
      <?php require __DIR__ . '/mensajes.php'; ?>
      <?= $contenido ?>
    </main>
  </div>
</div>
<script src="js/app.js"></script>
</body>
</html>
