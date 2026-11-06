<?php
/**
 * «boundary» IUFormCuenta — CU02 (registrar / modificar cuenta).   Responsable: Erika
 * ingresarDatos(...), presionarGuardar() -> ControlGestionarCuentas::guardarCuenta()
 * Campos = columnas de persona y usuario.
 * @var array|null $cuenta  @var array $roles  @var array $errores
 */
$v = fn(string $c) => e($cuenta[$c] ?? '');
$nueva = empty($cuenta['id']);
?>
<h2><?= $nueva ? 'Nueva cuenta' : 'Modificar cuenta' ?></h2>
<form method="post" action="?r=cuentas/guardar" class="formulario ancho">
  <?= campoCsrf() ?>
  <input type="hidden" name="id" value="<?= $v('id') ?>">
  <fieldset class="bloque"><legend>Datos personales</legend>
    <div class="fila">
      <label><span class="obligatorio">Nombres</span><input name="nombres" maxlength="60" value="<?= $v('nombres') ?>" required><?= errorCampo($errores, 'nombres') ?></label>
      <label><span class="obligatorio">Apellidos</span><input name="apellidos" maxlength="60" value="<?= $v('apellidos') ?>" required><?= errorCampo($errores, 'apellidos') ?></label>
    </div>
    <div class="fila-3">
      <label><span class="obligatorio">CI</span><input name="ci" maxlength="15" value="<?= $v('ci') ?>" required><?= errorCampo($errores, 'ci') ?></label>
      <label><span class="obligatorio">Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" value="<?= $v('fecha_nacimiento') ?>" max="<?= date('Y-m-d') ?>" required><?= errorCampo($errores, 'fecha_nacimiento') ?></label>
      <label><span class="obligatorio">Sexo</span>
        <select name="sexo" required><option value="">Seleccione</option>
          <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($cuenta['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select><?= errorCampo($errores, 'sexo') ?></label>
    </div>
    <div class="fila">
      <label>Teléfono<input name="telefono" maxlength="20" value="<?= $v('telefono') ?>"></label>
      <label>Dirección<input name="direccion" maxlength="150" value="<?= $v('direccion') ?>"></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Cuenta de acceso</legend>
    <div class="fila">
      <label><span class="obligatorio">Correo electrónico</span><input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" required><?= errorCampo($errores, 'correo') ?></label>
      <label><span class="obligatorio">Rol</span>
        <select name="rol_id" required><option value="">Seleccione</option>
          <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= ($cuenta['rol_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option><?php endforeach; ?>
        </select><?= errorCampo($errores, 'rol_id') ?></label>
    </div>
    <?php if ($nueva): ?>
      <label><span class="obligatorio">Contraseña inicial</span><input type="password" name="contrasena" minlength="8" required>
        <small>Mínimo 8 caracteres. El usuario deberá cambiarla en su primer ingreso.</small><?= errorCampo($errores, 'contrasena') ?></label>
    <?php endif; ?>
  </fieldset>
  <div class="botones"><a class="btn secundario" href="?r=cuentas">Cancelar</a><button class="btn">Guardar</button></div>
</form>
