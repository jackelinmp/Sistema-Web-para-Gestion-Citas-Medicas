<?php
/**
 * «boundary» IUConocimiento — CU26 Base de conocimiento.
 * listarPreguntas() -> ControlGestionarBaseConocimiento::listarPreguntas()
 * @var array $preguntas  @var array $categorias  @var string $categoria  @var string $criterio  @var array $sinRespuesta
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2>Base de conocimiento</h2>
  <?php if (Sesion::puede('COMUNICACION', 'M')): ?>
    <a class="btn" href="?r=conocimiento/formulario">Nueva pregunta</a>
  <?php endif; ?>
</div>

<form class="filtros" method="get">
  <input type="hidden" name="r" value="conocimiento">
  <label>Categoría
    <select name="categoria">
      <option value="">Todas</option>
      <?php foreach ($categorias as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $categoria === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Buscar<input type="text" name="criterio" value="<?= e($criterio) ?>"></label>
  <button class="btn secundario">Filtrar</button>
</form>

<table class="tabla">
  <thead><tr><th>Categoría</th><th>Pregunta</th><th>Respuesta</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($preguntas as $p): ?>
    <?php $activa = in_array($p['estado'], [true, 't', 'true', 1, '1'], true); ?>
    <tr>
      <td><?= e($p['categoria']) ?></td>
      <td><?= e($p['pregunta']) ?></td>
      <td><?= e(mb_substr($p['respuesta'], 0, 80)) ?><?= mb_strlen($p['respuesta']) > 80 ? '…' : '' ?></td>
      <td><?= $activa ? 'Activa' : 'Inactiva' ?></td>
      <td class="acciones">
        <?php if (Sesion::puede('COMUNICACION', 'M')): ?>
          <a class="btn-enlace" href="?r=conocimiento/formulario&id=<?= (int) $p['id'] ?>">Editar</a>
          <form method="post" action="?r=conocimiento/estado" style="display:inline">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn-enlace"><?= $activa ? 'Desactivar' : 'Activar' ?></button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$preguntas): ?>
    <tr><td colspan="5" class="vacio">No hay preguntas.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php if ($sinRespuesta): ?>
  <h3>Preguntas que el asistente no supo responder</h3>
  <table class="tabla">
    <thead><tr><th>Pregunta</th><th>Veces</th><th>Última vez</th></tr></thead>
    <tbody>
    <?php foreach ($sinRespuesta as $s): ?>
      <tr>
        <td><?= e($s['texto']) ?></td>
        <td><?= (int) $s['veces'] ?></td>
        <td><?= fechaHora($s['ultima_vez']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
