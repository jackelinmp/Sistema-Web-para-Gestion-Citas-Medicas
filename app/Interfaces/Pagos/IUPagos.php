<?php
/**
 * «boundary» IUPagos — CU19 Procesar Pago en Línea: lista de pagos de citas.   Ciclo 1 · P7
 * aplicarFiltros(estado, criterio) -> ControlProcesarPago::listarPagos()
 * seleccionarPago(cita_id) -> ControlProcesarPago::mostrarDetalle()
 * @var array $pagos  @var array $filtros
 */
use App\Nucleo\Sesion;
$esPaciente = (Sesion::usuario()['rol'] ?? '') === 'PACIENTE';
?>
<div class="encabezado"><h2>Pagos de citas</h2><span class="suave">Pago en línea con tarjeta (modo de prueba)</span></div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="pagos">
  <?php if (!$esPaciente): ?><label>Buscar<input name="criterio" value="<?= e($filtros['criterio']) ?>" placeholder="Paciente o código de cita"></label><?php endif; ?>
  <label>Estado
    <select name="estado"><option value="">Todos</option>
      <?php foreach (['PENDIENTE' => 'Pendiente', 'PAGADO' => 'Pagado', 'ANULADO' => 'Anulado'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Filtrar</button>
</form>
<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Cita</th><th>Paciente</th><th>Especialidad</th><th>Fecha</th><th>Monto</th><th>Pago</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($pagos as $p): ?>
    <tr>
      <td><?= e($p['codigo']) ?></td><td><?= e($p['paciente']) ?></td><td><?= e($p['especialidad']) ?></td>
      <td class="sin-salto"><?= fecha($p['fecha']) ?> <?= hora($p['hora_inicio']) ?></td>
      <td class="sin-salto"><?= bs($p['monto']) ?></td><td><?= insignia($p['pago_estado']) ?></td>
      <td><a class="btn pequeno <?= $p['pago_estado'] === 'PENDIENTE' ? '' : 'secundario' ?>" href="?r=pagos/detalle&amp;cita_id=<?= (int) $p['cita_id'] ?>"><?= $p['pago_estado'] === 'PENDIENTE' ? 'Pagar' : 'Ver' ?></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pagos): ?><tr><td colspan="7" class="vacio">No hay pagos para los filtros elegidos.</td></tr><?php endif; ?>
  </tbody>
</table></div>
