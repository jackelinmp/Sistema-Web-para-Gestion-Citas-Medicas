<?php
/**
 * «boundary» IUFormMedico — CU09 (registrar / modificar médico).   Responsable: Erika
 * ingresarDatos(datos, especialidades), presionarGuardar() -> ControlGestionarMedicos::guardarMedico()
 * Al registrar se crea su cuenta con rol MEDICO (contraseña temporal = CI).
 * @var array|null $medico  @var array $especialidades  @var array $errores
 */
$v = fn(string $c) => e($medico[$c] ?? '');
$nuevo = empty($medico['persona_id']);
$elegidas = array_map('intval', (array) ($medico['especialidades'] ?? []));
?>
<h2><?= $nuevo ? 'Nuevo médico' : 'Modificar médico' ?></h2>
<form method="post" action="?r=medicos/guardar" class="formulario ancho">
  <?= campoCsrf() ?>
  <input type="hidden" name="persona_id" value="<?= $v('persona_id') ?>">
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
          <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($medico['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select><?= errorCampo($errores, 'sexo') ?></label>
    </div>
    <div class="fila">
      <label>Teléfono<input name="telefono" maxlength="20" value="<?= $v('telefono') ?>"></label>
      <label>Dirección<input name="direccion" maxlength="150" value="<?= $v('direccion') ?>"></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Datos profesionales</legend>
    <div class="fila">
      <label><span class="obligatorio">Matrícula profesional</span><input name="matricula" maxlength="20" value="<?= $v('matricula') ?>" required><?= errorCampo($errores, 'matricula') ?></label>
      <label><?= $nuevo ? '<span class="obligatorio">Correo electrónico</span>' : 'Correo electrónico' ?>
        <input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" <?= $nuevo ? 'required' : 'readonly' ?>><?= errorCampo($errores, 'correo') ?></label>
    </div>
    <div>
      <span class="obligatorio"><strong>Especialidades</strong></span>
      <div class="fila-3">
        <?php foreach ($especialidades as $esp): ?>
          <label><span><input type="checkbox" name="especialidades[]" value="<?= (int) $esp['id'] ?>" <?= in_array((int) $esp['id'], $elegidas, true) ? 'checked' : '' ?>> <?= e($esp['nombre']) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?= errorCampo($errores, 'especialidades') ?>
    </div>
  </fieldset>
  <div class="botones"><a class="btn secundario" href="?r=medicos">Cancelar</a><button class="btn">Guardar</button></div>
</form>
