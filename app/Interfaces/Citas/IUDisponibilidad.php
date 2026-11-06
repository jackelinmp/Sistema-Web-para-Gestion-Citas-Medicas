<?php
/**
 * «boundary» IUDisponibilidad — CU13 Verificar Disponibilidad Horaria.   Responsable: Jackeline
 * solicitarDisponibilidad() / seleccionarFiltros(especialidad_id, medico_id, fecha) -> ControlVerificarDisponibilidad::mostrarFiltros()
 * @var array $especialidades  @var array $medicos  @var array $filtros  @var array|null $resultado  @var string|null $error
 */
use App\Nucleo\Sesion;
?>
<h2>Disponibilidad de horarios</h2>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="disponibilidad">
  <label>Especialidad
    <select name="especialidad_id" required><option value="">Seleccione</option>
      <?php foreach ($especialidades as $esp): ?>
        <option value="<?= (int) $esp['id'] ?>" <?= $filtros['especialidad_id'] == $esp['id'] ? 'selected' : '' ?>><?= e($esp['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Médico
    <select name="medico_id"><option value="">Todos</option>
      <?php foreach ($medicos as $m): ?>
        <option value="<?= (int) $m['persona_id'] ?>" <?= $filtros['medico_id'] == $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Fecha<input type="date" name="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($filtros['fecha']) ?>" required></label>
  <button class="btn">Consultar</button>
</form>

<?php if ($error): ?>
  <div class="alerta error"><?= e($error) ?></div>
<?php elseif ($resultado !== null): ?>
  <?php foreach ($resultado as $fila): ?>
    <h3><?= e($fila['medico']['nombre']) ?> <small>· <?= fecha($filtros['fecha']) ?></small></h3>
    <?php if (!$fila['cupos']): ?>
      <p class="suave">Sin cupos libres en esta fecha (no atiende ese día o la agenda está llena).</p>
    <?php else: ?>
      <div class="cupos">
        <?php foreach ($fila['cupos'] as $c): ?>
          <?php if (Sesion::puede('CITAS', 'R')): ?>
            <a class="cupo" href="?r=citas/nueva&especialidad_id=<?= (int) $filtros['especialidad_id'] ?>&medico_id=<?= (int) $fila['medico']['persona_id'] ?>&fecha=<?= e($filtros['fecha']) ?>&hora_inicio=<?= hora($c['hora_inicio']) ?>"><?= hora($c['hora_inicio']) ?></a>
          <?php else: ?>
            <span class="cupo"><?= hora($c['hora_inicio']) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
