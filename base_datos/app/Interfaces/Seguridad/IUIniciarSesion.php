<?php
/**
 * «boundary» IUIniciarSesion — CU01 Iniciar Sesión (Ciclo 1 · P1).
 * ingresarCredenciales(correo, contrasena) -> ControlIniciarSesion::iniciarSesion()
 * olvidoContrasena()                       -> ControlIniciarSesion::mostrarRecuperacion()
 * @var string $correo  @var bool|null $bloqueado  (cuenta bloqueada: se deshabilita «Ingresar»)
 */
?>
<span class="etiqueta-cu">CU01</span>
<h2>Iniciar sesión</h2>
<p class="suave">Ingrese con la cuenta que le entregó la clínica.</p>
<form method="post" action="?r=sesion/iniciar" class="formulario">
  <?= campoCsrf() ?>
  <label>Correo electrónico
    <input type="email" name="correo" value="<?= e($correo) ?>" required autofocus autocomplete="username">
  </label>
  <label>Contraseña
    <span class="campo-clave">
      <input type="password" name="contrasena" required autocomplete="current-password">
      <button type="button" class="btn-icono" data-ver-clave title="Mostrar u ocultar" aria-label="Mostrar u ocultar contraseña"><?= icono('ojo', 18) ?></button>
    </span>
  </label>
  <button class="btn grande" <?= !empty($bloqueado) ? 'disabled aria-disabled="true"' : '' ?>>Ingresar</button>
  <p class="centrado"><a href="?r=sesion/recuperar">¿Olvidó su contraseña?</a></p>
</form>
