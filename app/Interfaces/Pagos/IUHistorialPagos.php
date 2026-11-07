<?php
/**
 * «boundary» IUHistorialPagos — CU20 Consultar Pagos y Comprobantes.   Ciclo 2 · P7 · Responsable: Jackeline
 * aplicarFiltros(criterio, estado, metodo, desde, hasta) -> ControlConsultarPagos::listarHistorial()
 * seleccionarComprobante(cita_id)                       -> ControlConsultarPagos::mostrarComprobante()
 * @var array $pagos  @var array $totales  @var array $filtros  @var bool $esPaciente
 */
use App\Controles\Pagos\ControlConsultarPagos;
$verPaciente = !$esPaciente || count($filtros['pacientes'] ?? []) > 1;   // el titular ve de quién es cada pago
?>
<div class="encabezado"><h2><?= $esPaciente ? 'Mis pagos y comprobantes' : 'Pagos y comprobantes' ?></h2></div>
<div class="indicadores">
  <?php foreach ($totales as $estado => $monto): ?>
    <div class="indicador"><div class="valor"><?= bs($monto) ?></div><div class="rotulo"><?= e(ucfirst(strtolower($estado))) ?></div></div>
  <?php endforeach; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="comprobantes">
  <?php if ($filtros['paciente_id']): ?><input type="hidden" name="paciente_id" value="<?= (int) $filtros['paciente_id'] ?>"><?php endif; ?>
  <?php if (!$esPaciente && !$filtros['paciente_id']): ?>
    <label>Buscar<input name="criterio" value="<?= e($filtros['criterio']) ?>" placeholder="Paciente, expediente, cita o comprobante"></label>
  <?php endif; ?>
  <label>Estado
    <select name="estado"><option value="">Todos</option>
      <?php foreach (ControlConsultarPagos::ESTADOS as $es): ?><option value="<?= $es ?>" <?= $filtros['estado'] === $es ? 'selected' : '' ?>><?= e(ucfirst(strtolower($es))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Método
    <select name="metodo"><option value="">Todos</option>
      <?php foreach (ControlConsultarPagos::METODOS as $m): ?><option value="<?= $m ?>" <?= $filtros['metodo'] === $m ? 'selected' : '' ?>><?= e(ucfirst(strtolower($m))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <button class="btn secundario">Filtrar</button>
</form>
<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Cita</th><?php if ($verPaciente): ?><th>Paciente</th><?php endif; ?><th>Especialidad</th><th>Monto</th><th>Método</th><th>Fecha de pago</th><th>Estado</th><th>Comprobante</th></tr></thead>
  <tbody>
  <?php foreach ($pagos as $p): ?>
    <tr>
      <td class="sin-salto"><?= e($p['codigo']) ?><br><span class="suave"><?= fecha($p['fecha']) ?> <?= hora($p['hora_inicio']) ?></span></td>
      <?php if ($verPaciente): ?><td><?= e($p['paciente']) ?> <span class="suave">(<?= e($p['nro_expediente']) ?>)</span></td><?php endif; ?>
      <td><?= e($p['especialidad']) ?></td>
      <td class="sin-salto"><?= bs($p['monto']) ?></td>
      <td><?= e($p['metodo'] ? ucfirst(strtolower($p['metodo'])) : '—') ?></td>
      <td class="sin-salto"><?= $p['fecha_pago'] ? fechaHora($p['fecha_pago']) : '—' ?></td>
      <td><?= insignia($p['pago_estado']) ?></td>
      <td class="acciones">
        <?php if ($p['nro_correlativo']): ?>
          <a href="?r=comprobantes/ver&amp;cita_id=<?= (int) $p['cita_id'] ?>"><?= e($p['nro_correlativo']) ?></a>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pagos): ?><tr><td colspan="8" class="vacio">No se encontraron pagos.</td></tr><?php endif; ?>
  </tbody>
</table></div>
