<?php
/**
 * «boundary» IUPreRegistroPaciente — pre-registro de paciente externo (CU05).
 * @var array $d  @var array $errores
 */
?>
<div class="encabezado"><h2>Pre-registro de paciente</h2></div>
<p class="suave">Si no tiene expediente en la clínica, complete sus datos. Recepción validará su carnet y activará su cuenta.</p>
<form method="post" action="?r=sesion/pre-registro/guardar" class="formulario">
  <?= campoCsrf() ?>
  <label>CI / Carnet<input name="ci" value="<?= e($d['ci'] ?? '') ?>" required></label><?= errorCampo($errores, 'ci') ?>
  <label>Fecha de nacimiento<input type="date" name="fecha_nacimiento" value="<?= e($d['fecha_nacimiento'] ?? '') ?>" required></label><?= errorCampo($errores, 'fecha_nacimiento') ?>
  <label>Teléfono (celular)<input name="telefono" value="<?= e($d['telefono'] ?? '') ?>" required placeholder="7xxxxxxxx"></label><?= errorCampo($errores, 'telefono') ?>
  <label>Correo (opcional)<input type="email" name="correo" value="<?= e($d['correo'] ?? '') ?>"></label><?= errorCampo($errores, 'correo') ?>
  <button class="btn">Enviar pre-registro</button>
  <a class="btn secundario" href="?r=sesion/formulario">Ya tengo cuenta</a>
</form>
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\app\Interfaces\Seguridad\IUPreRegistroPaciente.php"