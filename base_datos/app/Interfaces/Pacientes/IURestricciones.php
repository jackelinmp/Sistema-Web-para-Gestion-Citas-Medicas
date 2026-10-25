<?php
/**
 * «boundary» IURestricciones — CU06 Validar Edad y Restricciones (consulta).   Ciclo 1 · P2
 * buscarPaciente(criterio) / seleccionarPaciente(id) / cambiarFecha(fecha) -> ControlValidarRestricciones::consultarRestricciones()
 * @var string $fecha  @var string $criterio  @var array $pacientes  @var array|null $paciente  @var array $filas  @var int $edad
 */
use App\Nucleo\Sesion;
$esPaciente = (Sesion::usuario()['rol'] ?? '') === 'PACIENTE';
?>
<div class="encabezado"><h2>Edad y restricciones</h2><span class="suave">La edad se calcula a la fecha de la cita</span></div>

<?php if (!$paciente): ?>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="restricciones">
    <label>Buscar paciente<input name="criterio" value="<?= e($criterio) ?>" placeholder="CI, nombre o expediente" required autofocus></label>
    <label>Fecha de la cita<input type="date" name="fecha" value="<?= e($fecha) ?>"></label>
    <button class="btn">Buscar</button>
  </form>
  <?php if ($criterio !== ''): ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pacientes as $pa): ?>
      <tr><td><?= e($pa['nro_expediente']) ?></td><td><?= e($pa['apellidos'] . ', ' . $pa['nombres']) ?></td><td><?= e($pa['ci']) ?></td>
          <td><a class="btn pequeno" href="?<?= e(http_build_query(['r' => 'restricciones', 'paciente_id' => $pa['persona_id'], 'fecha' => $fecha])) ?>">Ver restricciones</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$pacientes): ?><tr><td colspan="4" class="vacio">No se encontró un paciente con los datos ingresados.</td></tr><?php endif; ?>
    </tbody></table></div>
  <?php endif; ?>
<?php else: ?>
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> (<?= e($paciente['nro_expediente']) ?>)
      <?php if (!$esPaciente): ?><a href="?r=restricciones">cambiar</a><?php endif; ?></dd>
    <dt>Edad</dt><dd><?= (int) $edad ?> años · sexo <?= $paciente['sexo'] === 'F' ? 'femenino' : 'masculino' ?></dd>
  </dl>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="restricciones">
    <?php if (!$esPaciente): ?><input type="hidden" name="paciente_id" value="<?= (int) $paciente['persona_id'] ?>"><?php endif; ?>
    <label>Fecha de la cita<input type="date" name="fecha" value="<?= e($fecha) ?>"></label>
    <button class="btn secundario">Recalcular</button>
  </form>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Especialidad</th><th>Rango de edad</th><th>Sexo</th><th>Costo</th><th>Resultado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr class="<?= $f['ok'] ? '' : 'inactivo' ?>">
        <td><?= e($f['nombre']) ?></td><td><?= e($f['rango']) ?></td><td><?= e($f['sexo']) ?></td><td><?= bs($f['costo']) ?></td>
        <td><?php if ($f['ok']): ?><span class="insignia activo">Permitida</span><?php else: ?><span class="insignia inactivo">No permitida</span> <small><?= e($f['motivo']) ?></small><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if (Sesion::puede('CITAS', 'R')): ?>
    <div class="acciones"><a class="btn" href="?<?= e(http_build_query(['r' => 'citas/nueva', 'paciente_id' => $paciente['persona_id'], 'fecha' => $fecha])) ?>">Agendar cita para este paciente</a></div>
  <?php endif; ?>
<?php endif; ?>
