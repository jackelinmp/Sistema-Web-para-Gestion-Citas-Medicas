<?php
/**
 * «boundary» IUFormConocimiento — CU26 Base de conocimiento (formulario).
 * @var array|null $pregunta  @var array $categorias  @var array $errores
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2><?= $pregunta ? 'Editar pregunta' : 'Nueva pregunta' ?></h2>
</div>

<form method="post" action="?r=conocimiento/guardar">
  <?= campoCsrf() ?>
  <?php if ($pregunta): ?><input type="hidden" name="id" value="<?= (int) $pregunta['id'] ?>"><?php endif; ?>
  <label>Categoría
    <select name="categoria">
      <?php foreach ($categorias as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= (($pregunta['categoria'] ?? '') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <?= errorCampo($errores, 'categoria') ?>
  </label>
  <label>Pregunta<textarea name="pregunta" required><?= e($pregunta['pregunta'] ?? '') ?></textarea><?= errorCampo($errores, 'pregunta') ?></label>
  <label>Respuesta<textarea name="respuesta" required><?= e($pregunta['respuesta'] ?? '') ?></textarea><?= errorCampo($errores, 'respuesta') ?></label>
  <label>Palabras clave (separadas por espacio)<input type="text" name="palabras_clave" value="<?= e($pregunta['palabras_clave'] ?? '') ?>"></label>
  <button class="btn">Guardar</button>
  <a class="btn secundario" href="?r=conocimiento">Cancelar</a>
</form>
