<?php
/**
 * «boundary» IUSolicitudExamen — CU23 solicitar un examen de laboratorio.   Ciclo 2 · P8 · Responsable: Jackeline
 * ingresarSolicitud(cita_id, tipo_examen_id), presionarSolicitar() -> ControlGestionarResultados::solicitarExamen()
 * @var array $paciente  @var array $citas  @var array $tipos  @var array $solicitud  @var array $errores
 */
?>
<h2>Solicitar examen · <?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> (<?= e($paciente['nro_expediente']) ?>)</h2>
<?php if (isset($errores['general'])): ?><div class="alerta error"><?= e($errores['general']) ?></div><?php endif; ?>
<?php if (!$citas): ?>
  <div class="alerta aviso">El paciente no tiene citas confirmadas o atendidas: solo en ellas se pueden solicitar exámenes.</div>
<?php elseif (!$tipos): ?>
  <div class="alerta aviso">No hay exámenes activos en el catálogo (tabla tipo_examen).</div>
<?php else: ?>
<form method="post" action="?r=laboratorio/solicitar/guardar" class="formulario">
  <?= campoCsrf() ?>
  <input type="hidden" name="paciente_id" value="<?= (int) $paciente['persona_id'] ?>">
  <label><span class="obligatorio">Cita en la que se solicita</span>
    <select name="cita_id" required><option value="">Seleccione</option>
      <?php foreach ($citas as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= ($solicitud['cita_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['codigo'] . ' · ' . date('d/m/Y', strtotime($c['fecha'])) . ' · ' . $c['especialidad'] . ' · ' . ucfirst(strtolower($c['estado']))) ?></option>
      <?php endforeach; ?>
    </select><?= errorCampo($errores, 'cita_id') ?></label>
  <label><span class="obligatorio">Examen</span>
    <select name="tipo_examen_id" required><option value="">Seleccione</option>
      <?php foreach ($tipos as $t): ?>
        <option value="<?= (int) $t['id'] ?>" <?= ($solicitud['tipo_examen_id'] ?? null) == $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?> · Bs <?= e(number_format((float) $t['costo'], 2)) ?></option>
      <?php endforeach; ?>
    </select><?= errorCampo($errores, 'tipo_examen_id') ?></label>
  <div class="botones"><a class="btn secundario" href="?r=laboratorio&amp;paciente_id=<?= (int) $paciente['persona_id'] ?>">Cancelar</a><button class="btn">Solicitar</button></div>
</form>
<?php endif; ?>
