<?php
/**
 * «boundary» IUIniciarSesion — CU01 Iniciar Sesión (Ciclo 1 · P1).
 * ingresarCredenciales(correo, contrasena) -> ControlIniciarSesion::iniciarSesion()
 * olvidoContrasena()                       -> ControlIniciarSesion::mostrarRecuperacion()
 * crearCuenta()                            -> ControlIniciarSesion::mostrarCrearCuenta()
 * @var string $correo  @var bool|null $bloqueado  (cuenta bloqueada: se deshabilita «Iniciar sesión»)
 */
?>
<span class="etiqueta-cu">CU01</span>
<h2>Iniciar sesión</h2>
<form method="post" action="?r=sesion/iniciar" class="formulario">
  <?= campoCsrf() ?>
  <label>Correo electrónico o CI
    <input type="text" name="correo" value="<?= e($correo) ?>" required autofocus autocomplete="username" placeholder="nombre@correo.com o 1234567">
  </label>
  <label>Contraseña
    <span class="campo-clave">
      <input type="password" name="contrasena" required autocomplete="current-password">
      <button type="button" class="btn-icono" data-ver-clave title="Mostrar u ocultar" aria-label="Mostrar u ocultar contraseña"><?= icono('ojo', 18) ?></button>
    </span>
  </label>
  <button class="btn grande" <?= !empty($bloqueado) ? 'disabled aria-disabled="true"' : '' ?>>Iniciar sesión</button>
  <p class="centrado"><a href="?r=sesion/recuperar">¿Olvidó su contraseña?</a></p>
</form>
<div class="acceso-nuevo">
  <a class="btn secundario grande" href="?r=sesion/registro">Crear cuenta</a>
  <p class="suave">¿Ya se atendió en la clínica? Cree su cuenta con su mismo CI y verá su historial.</p>
  <p class="suave"><a href="?r=asistente"><?= icono('chat', 16) ?> Asistente virtual</a></p>
</div>
