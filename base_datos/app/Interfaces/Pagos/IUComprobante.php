<?php
/**
 * «boundary» IUComprobante — CU20 comprobante de un pago realizado (imprimible).   Ciclo 2 · P7 · Responsable: Jackeline
 * presionarImprimir() -> window.print() del navegador
 * @var array $pago
 */
?>
<div class="tarjeta">
  <div class="encabezado">
    <h2 class="marca">Clínica Vida Salud</h2>
    <div><strong>Comprobante <?= e($pago['nro_correlativo']) ?></strong><br><span class="suave">Emitido: <?= fechaHora($pago['fecha_emision']) ?></span></div>
  </div>
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($pago['paciente']) ?> · CI <?= e($pago['ci']) ?> · <?= e($pago['nro_expediente']) ?></dd>
    <dt>Cita</dt><dd><?= e($pago['codigo']) ?> · <?= fecha($pago['fecha']) ?> <?= hora($pago['hora_inicio']) ?></dd>
    <dt>Especialidad</dt><dd><?= e($pago['especialidad']) ?></dd>
    <dt>Médico</dt><dd><?= e($pago['medico']) ?></dd>
    <dt>Método de pago</dt><dd><?= e(ucfirst(strtolower((string) $pago['metodo']))) ?></dd>
    <?php if ($pago['referencia_externa']): ?><dt>Referencia</dt><dd><?= e($pago['referencia_externa']) ?></dd><?php endif; ?>
    <dt>Fecha de pago</dt><dd><?= fechaHora($pago['fecha_pago']) ?></dd>
    <dt>Total pagado</dt><dd><?= e($pago['moneda']) ?> <?= e(number_format((float) $pago['monto'], 2, '.', ',')) ?></dd>
  </dl>
  <div class="botones">
    <a class="btn secundario" href="?r=comprobantes">Volver</a>
    <button type="button" class="btn" onclick="window.print()">Imprimir</button>
  </div>
</div>
