<?php
/**
 * «boundary» IUIniciarSesion — CU01.
 * Operaciones: ingresarCredenciales(correo, contrasena) -> ControlIniciarSesion::iniciarSesion()
 * @var string $correo
 */
?>
<form method="post" action="?r=sesion/iniciar" class="formulario">
  <?= campoCsrf() ?>
  <label>Correo electrónico
    <input type="email" name="correo" value="<?= e($correo) ?>" required autofocus>
  </label>
  <label>Contraseña
    <input type="password" name="contrasena" required>
  </label>
  <button class="btn">Ingresar</button>
</form>
