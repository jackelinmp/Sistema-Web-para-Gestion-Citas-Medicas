<?php
/**
 * «boundary» IUCambiarContrasena — CU01 (cambio de contraseña con la sesión iniciada;
 * obligatorio en el primer ingreso: usuario.debe_cambiar_contrasena).
 * ingresarContrasenas(actual, nueva, confirmacion), presionarGuardar() -> ControlIniciarSesion::cambiarContrasena()
 */
?>
<div class="encabezado"><h2>Cambiar contraseña</h2></div>
<p class="suave">Mínimo 8 caracteres, con letras y números, y distinta de la actual.</p>
<form method="post" action="?r=sesion/cambiar/guardar" class="formulario">
  <?= campoCsrf() ?>
  <label>Contraseña actual
    <input type="password" name="actual" required autofocus autocomplete="current-password">
  </label>
  <label>Contraseña nueva
    <span class="campo-clave">
      <input type="password" name="contrasena" minlength="8" required autocomplete="new-password">
      <button type="button" class="btn-icono" data-ver-clave aria-label="Mostrar u ocultar contraseña"><?= icono('ojo', 18) ?></button>
    </span>
  </label>
  <label>Confirmar contraseña nueva
    <input type="password" name="confirmacion" minlength="8" required autocomplete="new-password">
  </label>
  <div class="botones">
    <a class="btn secundario" href="?r=panel">Cancelar</a>
    <button class="btn">Guardar contraseña</button>
  </div>
</form>
