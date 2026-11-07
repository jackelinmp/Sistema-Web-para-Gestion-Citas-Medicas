<?php
/**
 * «boundary» IUVerificarOtp — verificación de OTP (CU05).
 * @var string $telefono  @var string|null $otpDebug  @var string|null $error
 */
?>
<div class="encabezado"><h2>Verificar código</h2></div>
<p class="suave">Enviamos un código de 6 dígitos por WhatsApp al número <?= e($telefono) ?>.</p>
<?php if ($otpDebug): ?><div class="alerta info">Modo debug — código OTP: <strong><?= e($otpDebug) ?></strong></div><?php endif; ?>
<?php if ($error): ?><div class="alerta error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="?r=sesion/verificar-otp/validar" class="formulario">
  <?= campoCsrf() ?>
  <label>Código de 6 dígitos<input name="codigo" maxlength="6" pattern="[0-9]{6}" required autofocus></label>
  <label>Correo para crear su cuenta (opcional)<input type="email" name="correo"></label>
  <button class="btn">Verificar</button>
</form>
<form method="post" action="?r=sesion/reenviar-otp" style="margin-top:8px">
  <?= campoCsrf() ?>
  <button class="btn secundario">Reenviar código</button>
</form>
<a class="btn secundario" href="?r=sesion/pre-registro">Volver</a>
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\app\Interfaces\Seguridad\IUVerificarOtp.php"