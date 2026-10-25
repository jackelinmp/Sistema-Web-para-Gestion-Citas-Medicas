<?php
/**
 * «boundary» IURecuperarContrasena — CU01 (recuperar contraseña por correo).
 * ingresarCorreo(correo), presionarEnviar() -> ControlIniciarSesion::solicitarRecuperacion()
 * @var string $correo
 * @var string|null $enlacePrueba  Solo en modo de pruebas (APP_DEBUG=true) y sin servidor de correo.
 */
?>
<span class="etiqueta-cu">CU01</span>
<h2>Recuperar contraseña</h2>
<p class="suave">Escriba el correo de su cuenta y le enviaremos un enlace para crear una contraseña nueva.</p>
<form method="post" action="?r=sesion/recuperar/enviar" class="formulario">
  <?= campoCsrf() ?>
  <label>Correo electrónico
    <input type="email" name="correo" value="<?= e($correo ?? '') ?>" required autofocus autocomplete="username">
  </label>
  <button class="btn grande">Enviar enlace</button>
  <p class="centrado"><a href="?r=sesion/formulario">Volver a iniciar sesión</a></p>
</form>
<?php if (!empty($enlacePrueba)): ?>
  <div class="alerta info">
    <strong>Modo de pruebas:</strong> el correo no está configurado, use este enlace:<br>
    <a class="enlace-largo" href="<?= e($enlacePrueba) ?>"><?= e($enlacePrueba) ?></a>
  </div>
<?php endif; ?>
