<?php
/**
 * «boundary» IUPreRegistroPaciente — pre-registro de paciente externo (CU05).
 * @var array $d  @var array $errores  @var string|null $otpDebug
 */
?>
<div class="encabezado"><h2>Pre-registro de paciente</h2></div>
<p class="suave">Si no tiene expediente en la clínica, complete sus datos para reservar una cita. Le enviaremos un código de verificación por WhatsApp.</p>
<?php if ($otpDebug): ?><div class="alerta info">Modo debug — código OTP: <strong><?= e($otpDebug) ?></strong></div><?php endif; ?>
<form method="post" action="?r=sesion/pre-registro/guardar" class="formulario">
  <?= campoCsrf() ?>
  <label>CI / Carnet<input name="ci" value="<?= e($d['ci'] ?? '') ?>" required></label><?= errorCampo($errores, 'ci') ?>
  <label>Fecha de nacimiento<input type="date" name="fecha_nacimiento" value="<?= e($d['fecha_nacimiento'] ?? '') ?>" required></label><?= errorCampo($errores, 'fecha_nacimiento') ?>
  <label>Teléfono (WhatsApp)<input name="telefono" value="<?= e($d['telefono'] ?? '') ?>" required placeholder="7xxxxxxxx"></label><?= errorCampo($errores, 'telefono') ?>
  <label>Correo (opcional)<input type="email" name="correo" value="<?= e($d['correo'] ?? '') ?>"></label><?= errorCampo($errores, 'correo') ?>
  <button class="btn">Enviar código</button>
  <a class="btn secundario" href="?r=sesion/formulario">Ya tengo cuenta</a>
</form>
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\app\Interfaces\Seguridad\IUPreRegistroPaciente.php"