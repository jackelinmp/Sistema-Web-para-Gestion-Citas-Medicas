<?php
/**
 * «boundary» IUReportes — CU18 Reportes operativos (RF13).
 * elegirReporte(tipo, desde, hasta [, estado, medico_id, especialidad_id]) -> ControlGenerarReportes::mostrarReportes()
 * descargarExcel() / descargarPdf()                                         -> ControlGenerarReportes::exportarReporte(formato)
 * @var array $filtros  @var array $tipos  @var array $estados  @var array $medicos  @var array $especialidades
 * @var string|null $error  @var array|null $resumen  @var array $filas
 */
$consulta = fn(string $ruta, array $extra = []) => '?' . e(http_build_query(['r' => $ruta] + $extra + array_filter($filtros)));
$total = array_sum(array_column($filas, 'total'));
?>
<div class="encabezado">
  <h2>Reportes operativos</h2>
  <?php if (!$error && $filas): ?>
    <span class="acciones">
      <a class="btn secundario" href="<?= $consulta('reportes/exportar', ['formato' => 'excel']) ?>">Descargar Excel</a>
      <a class="btn secundario" href="<?= $consulta('reportes/exportar', ['formato' => 'pdf']) ?>">Descargar PDF</a>
    </span>
  <?php endif; ?>
</div>

<nav class="pestanas">
  <?php foreach ($tipos as $clave => $titulo): ?>
    <a href="?<?= e(http_build_query(['r' => 'reportes', 'tipo' => $clave, 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta']])) ?>"
       class="<?= $filtros['tipo'] === $clave ? 'activa' : '' ?>"><?= e($titulo) ?></a>
  <?php endforeach; ?>
</nav>

<form class="filtros" method="get">
  <input type="hidden" name="r" value="reportes">
  <input type="hidden" name="tipo" value="<?= e($filtros['tipo']) ?>">
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>" required></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>" required></label>
  <?php if ($filtros['tipo'] === 'citas'): ?>
    <label>Estado<select name="estado"><option value="">Todos</option>
      <?php foreach ($estados as $es): ?><option value="<?= $es ?>" <?= $filtros['estado'] === $es ? 'selected' : '' ?>><?= e(ucfirst(strtolower($es))) ?></option><?php endforeach; ?>
    </select></label>
    <label>Médico<select name="medico_id"><option value="">Todos</option>
      <?php foreach ($medicos as $m): ?><option value="<?= (int) $m['persona_id'] ?>" <?= $filtros['medico_id'] === (int) $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
    </select></label>
    <label>Especialidad<select name="especialidad_id"><option value="">Todas</option>
      <?php foreach ($especialidades as $es): ?><option value="<?= (int) $es['id'] ?>" <?= $filtros['especialidad_id'] === (int) $es['id'] ? 'selected' : '' ?>><?= e($es['nombre']) ?></option><?php endforeach; ?>
    </select></label>
  <?php endif; ?>
  <button class="btn secundario">Generar</button>
  <span class="periodos-rapidos">
    <?php $lunes = date('Y-m-d', strtotime('monday this week'));
    foreach (['Hoy' => [date('Y-m-d'), date('Y-m-d')], 'Esta semana' => [$lunes, date('Y-m-d', strtotime($lunes . ' +6 day'))],
              'Este mes' => [date('Y-m-01'), date('Y-m-t')]] as $titulo => [$d, $h]): ?>
      <a class="btn pequeno secundario" href="?<?= e(http_build_query(['r' => 'reportes', 'tipo' => $filtros['tipo'], 'desde' => $d, 'hasta' => $h])) ?>"><?= $titulo ?></a>
    <?php endforeach; ?>
  </span>
</form>

<?php if ($error): ?>
  <div class="alerta error"><?= e($error) ?></div>
<?php else: ?>
  <div class="indicadores">
    <div class="indicador"><div class="valor"><?= (int) $resumen['citas'] ?></div><div class="rotulo">Citas del periodo</div></div>
    <div class="indicador"><div class="valor"><?= (int) $resumen['atendidas'] ?></div><div class="rotulo">Atendidas</div></div>
    <div class="indicador"><div class="valor"><?= (int) $resumen['canceladas'] ?></div><div class="rotulo">Canceladas</div></div>
    <div class="indicador"><div class="valor"><?= (int) $resumen['inasistencias'] ?></div><div class="rotulo">Inasistencias</div></div>
    <div class="indicador"><div class="valor"><?= (int) $resumen['pendientes'] ?></div><div class="rotulo">Por atender</div></div>
  </div>

  <div class="tabla-contenedor"><table class="tabla">
  <?php if ($filtros['tipo'] === 'citas'): ?>
    <thead><tr><th>Fecha</th><th>Hora</th><th>Código</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td class="sin-salto"><?= e($f['fecha']) ?></td><td><?= e($f['hora']) ?></td><td class="sin-salto"><?= e($f['codigo']) ?></td><td><?= e($f['paciente']) ?></td>
        <td><?= e($f['medico']) ?></td><td><?= e($f['especialidad']) ?></td><td><?= insignia($f['estado']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($filas): ?><tr><td colspan="7"><strong>Total: <?= count($filas) ?> cita(s)</strong></td></tr><?php endif; ?>
    </tbody>
  <?php elseif ($filtros['tipo'] === 'pacientes'): ?>
    <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th class="num">Edad</th><th>Sexo</th><th>Registrado</th><th class="num">Citas</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td><?= e($f['expediente']) ?></td><td><?= e($f['paciente']) ?></td><td><?= e($f['ci']) ?></td><td class="num"><?= (int) $f['edad'] ?></td>
        <td><?= e($f['sexo']) ?></td><td><?= e($f['registrado']) ?></td><td class="num"><?= (int) $f['citas'] ?></td></tr>
    <?php endforeach; ?>
    <?php if ($filas): ?><tr><td colspan="7"><strong>Total: <?= count($filas) ?> paciente(s)</strong></td></tr><?php endif; ?>
    </tbody>
  <?php elseif ($filtros['tipo'] === 'productividad'): ?>
    <thead><tr><th>Médico</th><th class="num">Citas</th><th class="num">Atendidas</th><th class="num">Canceladas</th>
      <th class="num">Inasistencias</th><th class="num">% inasistencia</th><th class="num">Ingresos</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td><?= e($f['medico']) ?></td><td class="num"><?= (int) $f['citas'] ?></td><td class="num"><?= (int) $f['atendidas'] ?></td>
        <td class="num"><?= (int) $f['canceladas'] ?></td><td class="num"><?= (int) $f['inasistencias'] ?></td>
        <td class="num"><?= $f['pct_inasistencia'] === null ? '—' : e($f['pct_inasistencia']) . ' %' ?></td>
        <td class="num sin-salto"><?= bs($f['ingresos']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  <?php elseif ($filtros['tipo'] === 'ingresos'): ?>
    <thead><tr><th>Día</th><th>Método</th><th class="num">Pagos</th><th class="num">Total</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td><?= fecha($f['dia']) ?></td><td><?= e(ucfirst(strtolower((string) $f['metodo']))) ?></td>
        <td class="num"><?= (int) $f['pagos'] ?></td><td class="num sin-salto"><?= bs($f['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if ($filas): ?><tr><td colspan="3"><strong>Total del periodo</strong></td><td class="num sin-salto"><strong><?= bs($total) ?></strong></td></tr><?php endif; ?>
    </tbody>
  <?php elseif ($filtros['tipo'] === 'carga'): ?>
    <thead><tr><th>Médico</th><th>Especialidades</th><th class="num">Horas de jornada</th><th class="num">Citas</th>
      <th class="num">Horas ocupadas</th><th class="num">% ocupación</th><th class="num">Días ausente</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td><?= e($f['medico']) ?></td><td><small><?= e($f['especialidades'] ?? '') ?></small></td>
        <td class="num"><?= e($f['horas_jornada']) ?></td><td class="num"><?= (int) $f['citas'] ?></td>
        <td class="num"><?= e($f['horas_citas']) ?></td>
        <td class="num"><?= $f['pct_ocupacion'] === null ? '—' : e($f['pct_ocupacion']) . ' %' ?></td>
        <td class="num"><?= (int) $f['dias_ausente'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  <?php else: ?>
    <thead><tr><th>Especialidad</th><th class="num">Citas que debían ocurrir</th><th class="num">Inasistencias</th><th class="num">% inasistencia</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
      <tr><td><?= e($f['especialidad']) ?></td><td class="num"><?= (int) $f['citas_vencidas'] ?></td>
        <td class="num"><?= (int) $f['inasistencias'] ?></td><td class="num"><?= e($f['pct_inasistencia']) ?> %</td></tr>
    <?php endforeach; ?>
    </tbody>
  <?php endif; ?>
  <?php if (!$filas): ?><tbody><tr><td colspan="7" class="vacio">No hay datos en el periodo elegido.</td></tr></tbody><?php endif; ?>
  </table></div>
  <?php if ($filtros['tipo'] === 'carga'): ?>
    <p class="suave">Horas de jornada = horas que su horario semanal (CU11) le asigna en el periodo. Ocupación = horas de citas no canceladas ÷ horas de jornada.
      Días ausente = días de atención cubiertos por un bloqueo de agenda (CU12: vacaciones, permiso, reunión).</p>
  <?php endif; ?>
  <?php if ($filtros['tipo'] === 'citas'): ?>
    <p class="suave">Para la agenda diaria de un médico, elija el mismo día en «Desde» y «Hasta» y seleccione el médico.</p>
  <?php endif; ?>
  <?php if ($filtros['tipo'] === 'productividad'): ?>
    <p class="suave">% inasistencia = inasistencias ÷ (atendidas + inasistencias). Ingresos = pagos cobrados de esas citas (sin reembolsos).</p>
  <?php endif; ?>
<?php endif; ?>
