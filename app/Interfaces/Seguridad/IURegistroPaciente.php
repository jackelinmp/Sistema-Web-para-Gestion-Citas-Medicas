<?php
/**
 * «boundary» IURegistroPaciente — paciente nuevo que se registra en línea (extensión de CU01 y CU05, Ciclo 2).
 * ingresarDatos(...), presionarRegistrarme() -> ControlIniciarSesion::registrarPaciente()
 * @var array $d  @var array $errores
 */
$v = fn(string $c) => e($d[$c] ?? '');
$err = fn(string $c) => errorCampo($errores, $c);
?>
<h2>Soy paciente nuevo</h2>
<p class="suave">Si nunca se atendió en la clínica, cree su cuenta y agende su primera cita. Si ya se atendió antes,
  <a href="?r=sesion/formulario">inicie sesión</a> o pida su acceso en recepción.</p>
<form method="post" action="?r=sesion/registro/guardar" class="formulario">
  <?= campoCsrf() ?>
  <div class="fila">
    <label><span class="obligatorio">Nombres</span><input name="nombres" maxlength="60" value="<?= $v('nombres') ?>" required><?= $err('nombres') ?></label>
    <label><span class="obligatorio">Apellidos</span><input name="apellidos" maxlength="60" value="<?= $v('apellidos') ?>" required><?= $err('apellidos') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">CI</span><input name="ci" maxlength="15" value="<?= $v('ci') ?>" required><?= $err('ci') ?></label>
    <label><span class="obligatorio">Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>" required><?= $err('fecha_nacimiento') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">Sexo</span>
      <select name="sexo" required><option value="">Seleccione</option>
        <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($d['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
      </select><?= $err('sexo') ?></label>
    <label><span class="obligatorio">Teléfono</span><input name="telefono" maxlength="20" value="<?= $v('telefono') ?>" required><?= $err('telefono') ?></label>
  </div>
  <label>Dirección<input name="direccion" maxlength="150" value="<?= $v('direccion') ?>"></label>
  <fieldset class="bloque"><legend>Contacto de emergencia</legend>
    <label><span class="obligatorio">Nombre</span><input name="contacto_emergencia_nombre" maxlength="100" value="<?= $v('contacto_emergencia_nombre') ?>" required><?= $err('contacto_emergencia_nombre') ?></label>
    <div class="fila">
      <label><span class="obligatorio">Parentesco</span><input name="contacto_emergencia_parentesco" maxlength="30" value="<?= $v('contacto_emergencia_parentesco') ?>" required><?= $err('contacto_emergencia_parentesco') ?></label>
      <label><span class="obligatorio">Teléfono</span><input name="contacto_emergencia_telefono" maxlength="20" value="<?= $v('contacto_emergencia_telefono') ?>" required><?= $err('contacto_emergencia_telefono') ?></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Cuenta de acceso</legend>
    <label><span class="obligatorio">Correo electrónico</span><input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" required autocomplete="username"><?= $err('correo') ?></label>
    <div class="fila">
      <label><span class="obligatorio">Contraseña</span><input type="password" name="contrasena" required minlength="8" autocomplete="new-password"><?= $err('contrasena') ?></label>
      <label><span class="obligatorio">Repita la contraseña</span><input type="password" name="confirmacion" required minlength="8" autocomplete="new-password"></label>
    </div>
    <small class="suave">Mínimo 8 caracteres, con letras y números.</small>
  </fieldset>
  <button class="btn grande">Registrarme</button>
  <p class="centrado"><a href="?r=sesion/formulario">Ya tengo cuenta</a></p>
</form>
