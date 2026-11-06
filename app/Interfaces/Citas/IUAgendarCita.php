<?php
/**
 * «boundary» IUAgendarCita — CU14 Agendar Cita Médica (RF06, RF07).   Responsable: Jackeline
 * Paso 1 buscarPaciente(criterio) / seleccionarPaciente(paciente_id)          -> ControlAgendarCita::mostrarFormulario()
 * Paso 2 seleccionarEspecialidad(especialidad_id, fecha) («include» CU06)     -> ControlAgendarCita::mostrarFormulario()
 * Paso 3 seleccionarCupo(medico_id, hora_inicio) («include» CU13)             -> ControlAgendarCita::mostrarFormulario()
 * Paso 4 presionarConfirmar(motivo_consulta)                                  -> ControlAgendarCita::confirmarCita()
 * @var array $p  @var array $pacientes  @var array|null $paciente  @var array $especialidades
 * @var array $medicos  @var array|null $cupos  @var array|null $resumen  @var string|null $error
 */
use App\Nucleo\Sesion;

$paso = !$p['paciente_id'] ? 1 : (!$p['especialidad_id'] ? 2 : (!$resumen ? 3 : 4));
$clase = fn(int $n) => $n < $paso ? 'hecho' : ($n === $paso ? 'actual' : '');
$base = ['r' => 'citas/nueva', 'paciente_id' => $p['paciente_id'], 'fecha' => $p['fecha']];
$enlace = fn(array $extra) => '?' . e(http_build_query($base + $extra));
?>
<h2>Agendar cita</h2>
<ol class="pasos">
  <li class="<?= $clase(1) ?>">Paciente</li><li class="<?= $clase(2) ?>">Especialidad y fecha</li>
  <li class="<?= $clase(3) ?>">Horario</li><li class="<?= $clase(4) ?>">Confirmar</li>
</ol>
<?php if ($error): ?><div class="alerta error"><?= e($error) ?></div><?php endif; ?>

<?php if ($paso === 1): ?>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="citas/nueva">
    <?php foreach (['especialidad_id', 'medico_id', 'hora_inicio'] as $k): if ($p[$k]): ?>
      <input type="hidden" name="<?= $k ?>" value="<?= e($p[$k]) ?>">
    <?php endif; endforeach; ?>
    <input type="hidden" name="fecha" value="<?= e($p['fecha']) ?>">
    <label>Buscar paciente<input name="criterio" value="<?= e($p['criterio']) ?>" placeholder="CI, nombre o expediente" required autofocus></label>
    <button class="btn">Buscar</button>
    <?php if (Sesion::puede('PACIENTES', 'R')): ?><a class="btn secundario" href="?r=pacientes/formulario">Registrar paciente</a><?php endif; ?>
  </form>
  <?php if ($p['criterio'] !== ''): ?>
    <table class="tabla">
      <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th>Nacimiento</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pacientes as $pa): ?>
        <tr><td><?= e($pa['nro_expediente']) ?></td><td><?= e($pa['apellidos'] . ', ' . $pa['nombres']) ?></td>
            <td><?= e($pa['ci']) ?></td><td><?= fecha($pa['fecha_nacimiento']) ?></td>
            <td class="acciones"><a class="btn pequeno" href="?<?= e(http_build_query(['r' => 'citas/nueva', 'paciente_id' => $pa['persona_id'],
                'fecha' => $p['fecha'], 'especialidad_id' => $p['especialidad_id'], 'medico_id' => $p['medico_id'], 'hora_inicio' => $p['hora_inicio']])) ?>">Seleccionar</a></td></tr>
      <?php endforeach; ?>
      <?php if (!$pacientes): ?><tr><td colspan="5" class="vacio">No se encontró un paciente con los datos ingresados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else: ?>
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> (<?= e($paciente['nro_expediente']) ?>)
      <a href="?r=citas/nueva">cambiar</a></dd>
  </dl>

  <form class="filtros" method="get">
    <input type="hidden" name="r" value="citas/nueva">
    <input type="hidden" name="paciente_id" value="<?= (int) $p['paciente_id'] ?>">
    <label>Fecha<input type="date" name="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($p['fecha']) ?>" required></label>
    <label>Especialidad
      <select name="especialidad_id" required><option value="">Seleccione</option>
        <?php foreach ($especialidades as $esp): ?>
          <option value="<?= (int) $esp['id'] ?>" <?= $p['especialidad_id'] == $esp['id'] ? 'selected' : '' ?>><?= e($esp['nombre']) ?> · <?= bs($esp['costo_consulta']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php if ($medicos): ?>
      <label>Médico
        <select name="medico_id"><option value="">Todos</option>
          <?php foreach ($medicos as $m): ?><option value="<?= (int) $m['persona_id'] ?>" <?= $p['medico_id'] == $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>
    <button class="btn secundario">Ver horarios</button>
  </form>

  <?php if ($cupos !== null && !$resumen): ?>
    <?php foreach ($cupos as $fila): ?>
      <h3><?= e($fila['medico']['nombre']) ?></h3>
      <?php if (!$fila['cupos']): ?>
        <p class="suave">Sin cupos libres el <?= fecha($p['fecha']) ?>. Pruebe otra fecha u otro médico.</p>
      <?php else: ?>
        <div class="cupos">
          <?php foreach ($fila['cupos'] as $c): ?>
            <a class="cupo" href="<?= $enlace(['especialidad_id' => $p['especialidad_id'], 'medico_id' => $fila['medico']['persona_id'], 'hora_inicio' => hora($c['hora_inicio'])]) ?>"><?= hora($c['hora_inicio']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($resumen): ?>
    <h3>Resumen de la cita</h3>
    <dl class="resumen">
      <dt>Especialidad</dt><dd><?= e($resumen['especialidad']) ?></dd>
      <dt>Médico</dt><dd><?= e($resumen['medico']) ?></dd>
      <dt>Fecha</dt><dd><?= fecha($p['fecha']) ?></dd>
      <dt>Hora</dt><dd><?= e($p['hora_inicio']) ?></dd>
      <dt>Costo de la consulta</dt><dd><?= bs($resumen['costo']) ?></dd>
    </dl>
    <form method="post" action="?r=citas/confirmar" class="formulario">
      <?= campoCsrf() ?>
      <?php foreach (['paciente_id', 'especialidad_id', 'medico_id', 'fecha', 'hora_inicio'] as $k): ?>
        <input type="hidden" name="<?= $k ?>" value="<?= e($p[$k]) ?>">
      <?php endforeach; ?>
      <label>Motivo de la consulta (opcional)<textarea name="motivo_consulta" maxlength="250" rows="2"></textarea></label>
      <div class="botones">
        <a class="btn secundario" href="<?= $enlace(['especialidad_id' => $p['especialidad_id']]) ?>">Elegir otro horario</a>
        <button class="btn">Confirmar cita</button>
      </div>
    </form>
  <?php endif; ?>
<?php endif; ?>
