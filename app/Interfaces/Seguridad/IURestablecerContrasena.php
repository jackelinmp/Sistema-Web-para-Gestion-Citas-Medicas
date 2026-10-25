<?php
/**
 * «boundary» IURestablecerContrasena — CU01 (nueva contraseña desde el enlace del correo).
 * ingresarContrasena(contrasena, confirmacion), presionarGuardar() -> ControlIniciarSesion::restablecerContrasena()
 * @var string $token
 */
?>
<span class="etiqueta-cu">CU01</span>
<h2>Nueva contraseña</h2>
<p class="suave">Mínimo 8 caracteres, con letras y números.</p>
<form method="post" action="?r=sesion/restablecer/guardar" class="formulario">
  <?= campoCsrf() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label>Contraseña nueva
    <span class="campo-clave">
      <input type="password" name="contrasena" minlength="8" required autofocus autocomplete="new-password">
      <button type="button" class="btn-icono" data-ver-clave aria-label="Mostrar u ocultar contraseña"><?= icono('ojo', 18) ?></button>
    </span>
  </label>
  <label>Confirmar contraseña
    <input type="password" name="confirmacion" minlength="8" required autocomplete="new-password">
  </label>
  <button class="btn grande">Guardar contraseña</button>
</form>
