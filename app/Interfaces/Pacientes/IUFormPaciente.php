<?php
/**
 * «boundary» IUFormPaciente — CU05 (registrar / modificar paciente).   Responsable: Erika
 * ingresarDatos(...), presionarGuardar() -> ControlGestionarPacientes::guardarPaciente()
 * Campos = columnas de persona y paciente. Si se registra correo se crea la cuenta (rol PACIENTE,
 * contraseña temporal = CI).
 * @var array|null $paciente  @var array $aseguradoras  @var array $errores
 */
$v = fn(string $c) => e($paciente[$c] ?? '');
$nuevo = empty($paciente['persona_id']);
?>
<h2><?= $nuevo ? 'Nuevo paciente' : 'Modificar paciente ' . $v('nro_expediente') ?></h2>
<form method="post" action="?r=pacientes/guardar" class="formulario ancho">
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
          <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($paciente['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
        </select><?= errorCampo($errores, 'sexo') ?></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Contacto</legend>
    <div class="fila-3">
      <label>Teléfono<input name="telefono" maxlength="20" value="<?= $v('telefono') ?>"></label>
      <label>Correo electrónico
        <input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" <?= empty($paciente['correo']) || !empty($errores) ? '' : 'readonly' ?>>
        <?= errorCampo($errores, 'correo') ?></label>
      <label>Dirección<input name="direccion" maxlength="150" value="<?= $v('direccion') ?>"></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Contacto de emergencia</legend>
    <div class="fila-3">
      <label><span class="obligatorio">Nombre</span><input name="contacto_emergencia_nombre" maxlength="100" value="<?= $v('contacto_emergencia_nombre') ?>" required><?= errorCampo($errores, 'contacto_emergencia_nombre') ?></label>
      <label><span class="obligatorio">Parentesco</span><input name="contacto_emergencia_parentesco" maxlength="30" value="<?= $v('contacto_emergencia_parentesco') ?>" required><?= errorCampo($errores, 'contacto_emergencia_parentesco') ?></label>
      <label><span class="obligatorio">Teléfono</span><input name="contacto_emergencia_telefono" maxlength="20" value="<?= $v('contacto_emergencia_telefono') ?>" required><?= errorCampo($errores, 'contacto_emergencia_telefono') ?></label>
    </div>
  </fieldset>
  <fieldset class="bloque"><legend>Seguro (opcional)</legend>
    <div class="fila">
      <label>Aseguradora
        <select name="aseguradora_id"><option value="">Sin seguro</option>
          <?php foreach ($aseguradoras as $a): ?><option value="<?= (int) $a['id'] ?>" <?= ($paciente['aseguradora_id'] ?? '') == $a['id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?></option><?php endforeach; ?>
        </select></label>
      <label>Número de póliza<input name="nro_poliza" maxlength="40" value="<?= $v('nro_poliza') ?>"><?= errorCampo($errores, 'nro_poliza') ?></label>
    </div>
  </fieldset>
  <div class="botones"><a class="btn secundario" href="?r=pacientes">Cancelar</a><button class="btn">Guardar</button></div>
</form>
