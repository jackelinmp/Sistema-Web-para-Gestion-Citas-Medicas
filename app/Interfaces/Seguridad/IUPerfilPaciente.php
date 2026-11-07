<?php
/**
 * «boundary» IUPerfilPaciente — perfil del paciente (CU01/CU05).
 * @var array|null $persona  @var array $errores  @var string|null $ok
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado"><h2>Mi perfil</h2></div>
<?php if ($ok): ?><div class="alerta ok"><?= e($ok) ?></div><?php endif; ?>
<?php foreach ($errores as $e): ?><div class="alerta error"><?= e($e) ?></div><?php endforeach; ?>
<form method="post" action="?r=perfil/guardar" class="formulario">
  <?= campoCsrf() ?>
  <label>Nombre<input value="<?= e($persona['nombres'] ?? '') ?>" disabled></label>
  <label>Apellido<input value="<?= e($persona['apellidos'] ?? '') ?>" disabled></label>
  <label>CI<input value="<?= e($persona['ci'] ?? '') ?>" disabled></label>
  <label>Teléfono<input name="telefono" value="<?= e($persona['telefono'] ?? '') ?>"></label>
  <label>Correo electrónico<input type="email" name="correo" value="<?= e($persona['correo'] ?? '') ?>"></label>
  <button class="btn">Guardar cambios</button>
  <a class="btn secundario" href="?r=sesion/cambiar">Cambiar contraseña</a>
</form>
