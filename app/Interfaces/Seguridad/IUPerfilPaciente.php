<?php
/**
 * «boundary» IUPerfilPaciente — Mi perfil (CU01 / CU24).
 * mostrarPerfil()  -> ControlPerfilPaciente::mostrarPerfil()
 * guardarPerfil()  -> ControlPerfilPaciente::guardarPerfil()
 * @var array $persona  @var bool $esPaciente  @var array $d  @var array $errores  @var bool $whatsappDisponible
 */
$v = fn(string $c) => e($d[$c] ?? '');
$err = fn(string $c) => errorCampo($errores, $c);
?>
<div class="encabezado"><h2>Mi perfil</h2></div>
<form method="post" action="?r=perfil/guardar" class="formulario">
  <?= campoCsrf() ?>
  <fieldset class="bloque">
    <legend>Mis datos</legend>
    <div class="fila">
      <label>Nombre<input value="<?= e(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? '')) ?>" disabled></label>
      <label>Cédula de identidad<input value="<?= e($persona['ci'] ?? '') ?>" disabled></label>
    </div>
    <small class="suave">Para corregir su nombre, CI o fecha de nacimiento, acuda a recepción con su documento.</small>
    <div class="fila">
      <label><span class="obligatorio">Correo electrónico</span><input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" required><?= $err('correo') ?></label>
      <label>Celular<input name="telefono" maxlength="20" inputmode="tel" placeholder="71234567" value="<?= $v('telefono') ?>"><?= $err('telefono') ?></label>
    </div>
    <label>Dirección<input name="direccion" maxlength="200" value="<?= $v('direccion') ?>"></label>
  </fieldset>

  <fieldset class="bloque">
    <legend>Avisos de citas</legend>
    <p class="suave">Las confirmaciones, cambios, cancelaciones y el recordatorio del día anterior se muestran en «Notificaciones»
      y se envían a su correo electrónico. Incluyen también las citas que usted sacó para otras personas.</p>
    <?php if ($whatsappDisponible): ?>
      <label class="casilla"><input type="checkbox" name="avisos_whatsapp" value="1" <?= !empty($d['whatsapp']) ? 'checked' : '' ?>>
        Recibir también por WhatsApp en mi teléfono celular</label>
    <?php endif; ?>
  </fieldset>

  <?php if ($esPaciente): ?>
  <fieldset class="bloque">
    <legend>Contacto de emergencia <small class="suave">(opcional)</small></legend>
    <p class="suave">Persona a la que la clínica contactará en caso de urgencia durante su atención. Si no lo registra ahora, se le solicitará en recepción.</p>
    <div class="fila">
      <label>Nombre<input name="contacto_emergencia_nombre" maxlength="100" value="<?= $v('ce_nombre') ?>"></label>
      <label>Parentesco<input name="contacto_emergencia_parentesco" maxlength="40" placeholder="Esposa, hijo, hermana…" value="<?= $v('ce_parent') ?>"></label>
    </div>
    <label>Celular<input name="contacto_emergencia_telefono" maxlength="20" inputmode="tel" value="<?= $v('ce_tel') ?>"><?= $err('contacto_emergencia_telefono') ?></label>
  </fieldset>
  <?php endif; ?>

  <div class="acciones">
    <button class="btn">Guardar cambios</button>
    <a class="btn secundario" href="?r=sesion/cambiar">Cambiar contraseña</a>
  </div>
</form>
