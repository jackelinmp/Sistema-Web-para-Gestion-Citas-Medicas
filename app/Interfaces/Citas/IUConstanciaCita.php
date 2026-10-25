<?php
/**
 * «boundary» IUConstanciaCita — CU14 paso 10: constancia de la cita registrada, con «Pagar ahora» (CU19).   Responsable: Jackeline
 * @var array $cita
 */
?>
<div class="encabezado">
  <h2>Cita médica <?= e($cita['codigo']) ?></h2>
  <?= insignia($cita['estado']) ?>
</div>
<div class="tarjeta">
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($cita['paciente']) ?> (<?= e($cita['nro_expediente']) ?>)</dd>
    <dt>Especialidad</dt><dd><?= e($cita['especialidad']) ?></dd>
    <dt>Médico</dt><dd><?= e($cita['medico']) ?></dd>
    <dt>Fecha</dt><dd><?= fecha($cita['fecha']) ?></dd>
    <dt>Horario</dt><dd><?= hora($cita['hora_inicio']) ?> – <?= hora($cita['hora_fin']) ?></dd>
    <dt>Motivo</dt><dd><?= e($cita['motivo_consulta'] ?: '—') ?></dd>
    <dt>Costo</dt><dd><?= bs($cita['costo_consulta']) ?> (pago pendiente)</dd>
  </dl>
  <div class="botones">
    <?php if (\App\Nucleo\Sesion::puede('PACIENTES', 'C')): ?><a class="btn secundario" href="?r=pacientes">Volver a pacientes</a><?php endif; ?>
    <a class="btn secundario" href="?r=citas/nueva">Nueva cita</a>
    <?php if (\App\Nucleo\Sesion::puede('PAGOS', 'C') && $cita['estado'] === 'PROGRAMADA'): ?>
      <a class="btn" href="?r=pagos/detalle&amp;cita_id=<?= (int) $cita['id'] ?>"><?= icono('tarjeta', 16) ?> Pagar ahora</a>
    <?php endif; ?>
  </div>
</div>
