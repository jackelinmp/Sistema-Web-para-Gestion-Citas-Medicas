<?php
/**
 * «boundary» IUDetallePago — CU19: detalle de la cita y su pago; resultado del cobro.   Ciclo 1 · P7
 * presionarPagarAhora(cita_id) -> ControlProcesarPago::iniciarPago()   (redirige a Stripe Checkout)
 * confirmarPorSeguro(cita_id, referencia) -> ControlProcesarPago::confirmarPorSeguro()
 * descargarComprobante(cita_id) -> ControlProcesarPago::descargarComprobante()
 * @var array $pago  @var bool $stripe  @var bool $puedePagar  @var bool $puedeSeguro
 */
?>
<div class="encabezado">
  <h2>Pago de la cita <?= e($pago['codigo']) ?></h2>
  <?= insignia($pago['pago_estado']) ?>
</div>
<div class="tarjeta">
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($pago['paciente']) ?> (<?= e($pago['nro_expediente']) ?>)</dd>
    <dt>Especialidad</dt><dd><?= e($pago['especialidad']) ?></dd>
    <dt>Médico</dt><dd><?= e($pago['medico']) ?></dd>
    <dt>Fecha y hora</dt><dd><?= fecha($pago['fecha']) ?> · <?= hora($pago['hora_inicio']) ?> – <?= hora($pago['hora_fin']) ?></dd>
    <dt>Estado de la cita</dt><dd><?= insignia($pago['cita_estado']) ?></dd>
    <dt>Monto a pagar</dt><dd><strong><?= bs($pago['monto']) ?></strong></dd>
    <?php if (!empty($pago['copago']) && $pago['copago'] > 0): ?>
      <dt>Copago (paciente)</dt><dd><?= bs($pago['copago']) ?> — <?= e(ucfirst(strtolower((string) $pago['copago_metodo']))) ?><?= $pago['copago_referencia'] ? ' (ref. ' . e($pago['copago_referencia']) . ')' : '' ?></dd>
      <dt>Cubierto por seguro</dt><dd><?= bs($pago['monto'] - $pago['copago']) ?></dd>
    <?php endif; ?>
    <?php if ($pago['pago_estado'] === 'PAGADO'): ?>
      <dt>Método</dt><dd><?= $pago['metodo'] === 'STRIPE' ? 'Tarjeta (Stripe)' : e(ucfirst(strtolower((string) $pago['metodo']))) ?></dd>
      <dt>Referencia</dt><dd><?= e($pago['referencia_externa']) ?></dd>
      <dt>Comprobante</dt><dd><?= e($pago['nro_correlativo']) ?> · <?= fechaHora($pago['fecha_pago']) ?></dd>
    <?php endif; ?>
  </dl>

  <?php if ($pago['pago_estado'] === 'PENDIENTE' && !$puedePagar): ?>
    <div class="alerta aviso">Esta cita no tiene pagos pendientes de cobro en línea.</div>
  <?php endif; ?>

  <div class="botones">
    <a class="btn secundario" href="?r=pagos">Volver a pagos</a>
    <?php if ($pago['pago_estado'] === 'PAGADO'): ?>
      <a class="btn" href="?r=pagos/comprobante&amp;cita_id=<?= (int) $pago['cita_id'] ?>" target="_blank" rel="noopener"><?= icono('tarjeta', 16) ?> Descargar comprobante (PDF)</a>
    <?php endif; ?>
    <?php if ($puedePagar): ?>
      <form method="post" action="?r=pagos/iniciar" style="display:inline"><?= campoCsrf() ?>
        <input type="hidden" name="cita_id" value="<?= (int) $pago['cita_id'] ?>">
        <button class="btn" <?= $stripe ? '' : 'disabled title="Falta configurar STRIPE_SECRET_KEY"' ?>><?= icono('tarjeta', 16) ?> Pagar ahora con tarjeta</button>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($puedePagar && !$stripe): ?><p class="suave">El pago en línea no está configurado (falta <code>STRIPE_SECRET_KEY</code>).</p><?php endif; ?>
  <?php if ($puedePagar): ?><p class="suave">Será redirigido al formulario seguro de Stripe. Tarjeta de prueba: <code>4242 4242 4242 4242</code>, cualquier fecha futura y CVC.</p><?php endif; ?>

  <?php if ($puedeSeguro): ?>
    <hr>
    <h3>Paciente asegurado: <?= e($pago['aseguradora']) ?></h3>
    <form method="post" action="?r=pagos/seguro" class="filtros"><?= campoCsrf() ?>
      <input type="hidden" name="cita_id" value="<?= (int) $pago['cita_id'] ?>">
      <label>Referencia de la póliza<input name="referencia" value="<?= e($pago['nro_poliza']) ?>" required maxlength="100"></label>
      <label>Copago del paciente (0 si el seguro cubre todo)<input type="number" step="0.01" min="0" name="copago" value="0"></label>
      <label>Cómo se cobró el copago
        <select name="copago_metodo">
          <option value="">Sin copago</option>
          <option value="EFECTIVO">Efectivo</option>
          <option value="QR">QR</option>
          <option value="TRANSFERENCIA">Transferencia</option>
          <option value="TARJETA">Tarjeta</option>
        </select>
      </label>
      <label>Referencia del copago (opcional)<input name="copago_referencia" maxlength="100"></label>
      <button class="btn secundario">Confirmar por seguro</button>
    </form>
  <?php endif; ?>

  <?php if ($puedePagar && $pago['pago_estado'] === 'PENDIENTE' && $esPersonal): ?>
    <hr>
    <h3>Pago en recepcion (efectivo, QR o transferencia)</h3>
    <form method="post" action="?r=pagos/registrar" class="filtros"><?= campoCsrf() ?>
      <input type="hidden" name="cita_id" value="<?= (int) $pago['cita_id'] ?>">
      <label>Metodo
        <select name="metodo" required>
          <option value="EFECTIVO">Efectivo</option>
          <option value="QR">QR</option>
          <option value="TRANSFERENCIA">Transferencia</option>
        </select>
      </label>
      <label>Referencia (opcional)<input name="referencia" maxlength="100" placeholder="Nro. de operacion / comprobante"></label>
      <button class="btn secundario">Registrar pago</button>
    </form>
    <p class="suave">Para QR: mostrar el codigo QR de la clinica al paciente; al confirmar el pago, registrarlo aqui.</p>
  <?php endif; ?>
</div>
