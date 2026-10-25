<?php
/**
 * «boundary» IUBitacora — Bitácora de auditoría (solo lectura, RN14).   Ciclo 1 · P1
 * solicitarBitacora() / aplicarFiltros(desde, hasta, usuario, accion, ip) -> ControlGestionarBitacora::consultarBitacora()
 * @var array $filtros  @var array $registros  @var int $total  @var int $pagina  @var int $paginas  @var array $acciones
 */
$consulta = http_build_query(['r' => 'bitacora', 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta'],
                              'usuario' => $filtros['usuario'], 'accion' => $filtros['accion'], 'ip' => $filtros['ip']]);
?>
<div class="encabezado">
  <h2>Bitácora del sistema</h2>
  <span class="suave"><?= number_format($total, 0, ',', '.') ?> registros · no se pueden editar ni borrar</span>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="bitacora">
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <label>Usuario<input name="usuario" value="<?= e($filtros['usuario']) ?>" placeholder="Nombre o correo"></label>
  <label>Acción
    <select name="accion"><option value="">Todas</option>
      <?php foreach ($acciones as $a): ?>
        <option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e(ucfirst(strtolower(str_replace('_', ' ', $a)))) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>IP<input name="ip" value="<?= e($filtros['ip']) ?>" placeholder="Ej. 190.186" maxlength="45"></label>
  <button class="btn secundario">Filtrar</button>
</form>
<div class="tabla-contenedor">
<table class="tabla">
  <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Descripción</th><th>Tabla</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($registros as $r): ?>
    <tr>
      <td class="sin-salto"><?= fechaHora($r['fecha_hora']) ?></td>
      <td><?= e($r['nombre'] ?: 'Sistema / sin sesión') ?><br><small><?= e($r['correo']) ?></small></td>
      <td><span class="insignia neutra"><?= e(str_replace('_', ' ', $r['accion'])) ?></span></td>
      <td><?= e($r['descripcion']) ?></td>
      <td><?= e($r['tabla']) ?><?= $r['registro_id'] ? ' #' . (int) $r['registro_id'] : '' ?></td>
      <td class="sin-salto"><?= e($r['ip'] ?: 'No disponible') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$registros): ?><tr><td colspan="6" class="vacio">No se encontraron registros de bitácora para los filtros seleccionados</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if ($paginas > 1): ?>
<div class="paginacion">
  <?php if ($pagina > 1): ?><a class="btn secundario pequeno" href="?<?= e($consulta) ?>&amp;pagina=<?= $pagina - 1 ?>">‹ Anterior</a><?php endif; ?>
  <span class="suave">Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a class="btn secundario pequeno" href="?<?= e($consulta) ?>&amp;pagina=<?= $pagina + 1 ?>">Siguiente ›</a><?php endif; ?>
</div>
<?php endif; ?>
