<?php
/**
 * «boundary» IUOtraPersona — «Para otra persona» (CU14 + CU22): hijo, familiar u otra persona que irá a la consulta.
 * mostrarOtraPersona()                    -> ControlGestionarGrupoFamiliar::mostrarOtraPersona()
 * ingresarDatos(...), presionarContinuar() -> ControlGestionarGrupoFamiliar::registrarOtraPersona()
 * @var array $d  @var array $errores  @var array $parentescos
 */
$v = fn(string $c) => e($d[$c] ?? '');
$err = fn(string $c) => errorCampo($errores, $c);
?>
<h2>Cita para otra persona</h2>
<p class="suave">Datos del paciente que irá a la consulta. Si ya se atendió en la clínica, escriba su CI.</p>
<form method="post" action="?r=citas/otra-persona/guardar" class="formulario">
  <?= campoCsrf() ?>
  <div class="fila">
    <label>CI <small class="suave">(opcional si es menor de edad)</small><input name="ci" maxlength="15" value="<?= $v('ci') ?>"><?= $err('ci') ?></label>
    <label><span class="obligatorio">¿Quién es?</span>
      <select name="parentesco" required><option value="">Seleccione</option>
        <?php foreach ($parentescos as $k => $t): ?><option value="<?= $k ?>" <?= ($d['parentesco'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
      </select><?= $err('parentesco') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">Nombres</span><input name="nombres" maxlength="60" value="<?= $v('nombres') ?>" required><?= $err('nombres') ?></label>
    <label><span class="obligatorio">Apellidos</span><input name="apellidos" maxlength="60" value="<?= $v('apellidos') ?>" required><?= $err('apellidos') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>" required><?= $err('fecha_nacimiento') ?></label>
    <label><span class="obligatorio">Sexo</span>
      <select name="sexo" required><option value="">Seleccione</option>
        <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($d['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
      </select><?= $err('sexo') ?></label>
  </div>
  <small class="suave">Los avisos de esta cita le llegarán a usted.</small>
  <div class="botones">
    <a class="btn secundario" href="?r=citas/nueva">Cancelar</a>
    <button class="btn">Continuar</button>
  </div>
</form>
