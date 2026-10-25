<?php
/**
 * «boundary» IUFormEspecialidad — CU08 (registrar / modificar).
 * Campos = atributos de la entidad Especialidad usados en el formulario:
 *   nombre, descripcion, costo_consulta, edad_minima, edad_maxima, sexo_permitido
 * Operaciones: ingresarDatos(...), presionarGuardar() -> ControlGestionarEspecialidades::guardarEspecialidad()
 * @var array|null $especialidad
 * @var array $errores
 */
$v = fn(string $c) => e($especialidad[$c] ?? '');
$err = fn(string $c) => errorCampo($errores, $c);
$sexo = $especialidad['sexo_permitido'] ?? 'A';
?>
<h2><?= empty($especialidad['id']) ? 'Nueva especialidad' : 'Modificar especialidad' ?></h2>
<form method="post" action="?r=especialidades/guardar" class="formulario">
  <?= campoCsrf() ?>
  <input type="hidden" name="id" value="<?= $v('id') ?>">
  <label><span class="obligatorio">Nombre</span>
    <input name="nombre" maxlength="60" value="<?= $v('nombre') ?>" required>
    <?= $err('nombre') ?>
  </label>
  <label>Descripción
    <textarea name="descripcion" maxlength="250" rows="2"><?= $v('descripcion') ?></textarea>
  </label>
  <label><span class="obligatorio">Costo de la consulta (Bs)</span>
    <input name="costo_consulta" type="number" min="0.01" step="0.01" value="<?= $v('costo_consulta') ?>" required>
    <?= $err('costo_consulta') ?>
  </label>
  <div class="fila">
    <label><span class="obligatorio">Edad mínima</span>
      <input name="edad_minima" type="number" min="0" max="120" value="<?= $especialidad['edad_minima'] ?? '0' ?>" required>
    </label>
    <label>Edad máxima <small>(vacío = sin límite)</small>
      <input name="edad_maxima" type="number" min="0" max="120" value="<?= $v('edad_maxima') ?>">
    </label>
  </div>
  <?= $err('edad_minima') ?>
  <label><span class="obligatorio">Sexo permitido</span>
    <select name="sexo_permitido">
      <?php foreach (['A' => 'Ambos', 'F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= $sexo === $k ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <?= $err('sexo_permitido') ?>
  </label>
  <div class="botones">
    <a class="btn secundario" href="?r=especialidades">Cancelar</a>
    <button class="btn">Guardar</button>
  </div>
</form>
