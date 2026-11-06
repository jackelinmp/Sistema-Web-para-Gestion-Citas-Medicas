<?php
/**
 * «boundary» IUReportes — CU18 Reportes operativos.
 * elegirReporte(tipo, desde, hasta) -> ControlGenerarReportes::mostrarReportes()
 * exportarCsv()                     -> ControlGenerarReportes::exportarReporte()
 * @var array $filtros  @var array $tipos  @var string|null $error  @var array|null $resumen  @var array $filas
 */
$consulta = fn(string $ruta) => '?' . e(http_build_query(['r' => $ruta] + $filtros));
$total = array_sum(array_column($filas, 'total'));
?>
<div class="encabezado">
  <h2>Reportes operativos</h2>
  <?php if (!$error && $filas): ?><a class="btn secundario" href="<?= $consulta('reportes/exportar') ?>">Descargar CSV (Excel)</a><?php endif; ?>
</div>

<nav class="pestanas">
  <?php foreach ($tipos as $clave => $titulo): ?>
    <a href="?<?= e(http_build_query(['r' => 'reportes', 'tipo' => $clave] + $filtros)) ?>"
       class="<?= $filtros['tipo'] === $clave ? 'activa' : '' ?>"><?= e($titulo) ?></a>
  <?php endforeach; ?>
</nav>

<form class="filtros" method="get">
  <input type="hidden" name="r" value="reportes">
  <input type="hidden" name="tipo" value="<?= e($filtros['tipo']) ?>">
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>" required></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>" required></label>
  <button class="btn secundario">Generar</button>
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
  <?php if ($filtros['tipo'] === 'productividad'): ?>
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
  <?php if ($filtros['tipo'] === 'productividad'): ?>
    <p class="suave">% inasistencia = inasistencias ÷ (atendidas + inasistencias). Ingresos = pagos cobrados de esas citas (sin reembolsos).</p>
  <?php endif; ?>
<?php endif; ?>
