<?php
/**
 * «boundary» IUVerificarExistente — Puerta A: paciente existente (CI + fecha de nacimiento).
 * @var int $paso  @var array|null $persona  @var bool $tieneCuenta  @var string|null $error
 */
?>
<div class="encabezado"><h2>Ingresar como paciente existente</h2></div>
<?php if ($error): ?><div class="alerta error"><?= e($error) ?></div><?php endif; ?>

<?php if ($paso === 1): ?>
  <p class="suave">Ingrese su número de carnet y fecha de nacimiento para verificar su identidad.</p>
  <form method="post" action="?r=sesion/existente/verificar" class="formulario">
    <?= campoCsrf() ?>
    <label>CI / Carnet<input name="ci" required autofocus></label>
    <label>Fecha de nacimiento<input type="date" name="fecha_nacimiento" required></label>
    <button class="btn">Verificar</button>
    <a class="btn secundario" href="?r=sesion/formulario">Volver al login</a>
  </form>
<?php else: ?>
  <p class="suave">Hola, <strong><?= e($persona['nombres'] . ' ' . $persona['apellidos']) ?></strong>.</p>
  <input type="hidden" name="persona_id" value="<?= (int) $persona['id'] ?>">
  <input type="hidden" name="ci" value="<?= e($persona['ci']) ?>">
  <input type="hidden" name="fecha_nacimiento" value="<?= e($persona['fecha_nacimiento']) ?>">
  <?php if ($tieneCuenta): ?>
    <p class="suave">Ingrese su contraseña para entrar.</p>
    <form method="post" action="?r=sesion/existente/ingresar" class="formulario">
      <?= campoCsrf() ?>
      <input type="hidden" name="persona_id" value="<?= (int) $persona['id'] ?>">
      <input type="hidden" name="ci" value="<?= e($persona['ci']) ?>">
      <input type="hidden" name="fecha_nacimiento" value="<?= e($persona['fecha_nacimiento']) ?>">
      <label>Contraseña<input type="password" name="contrasena" required></label>
      <button class="btn">Ingresar</button>
    </form>
  <?php else: ?>
    <p class="suave">Aún no tiene cuenta web. Cree una contraseña y un correo para acceder.</p>
    <form method="post" action="?r=sesion/existente/ingresar" class="formulario">
      <?= campoCsrf() ?>
      <input type="hidden" name="persona_id" value="<?= (int) $persona['id'] ?>">
      <input type="hidden" name="ci" value="<?= e($persona['ci']) ?>">
      <input type="hidden" name="fecha_nacimiento" value="<?= e($persona['fecha_nacimiento']) ?>">
      <label>Correo electrónico<input type="email" name="correo" required></label>
      <label>Contraseña<input type="password" name="contrasena" required></label>
      <label>Confirmar contraseña<input type="password" name="confirmacion" required></label>
      <button class="btn">Crear cuenta e ingresar</button>
    </form>
  <?php endif; ?>
<?php endif; ?>
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\app\Interfaces\Seguridad\IUVerificarExistente.php"