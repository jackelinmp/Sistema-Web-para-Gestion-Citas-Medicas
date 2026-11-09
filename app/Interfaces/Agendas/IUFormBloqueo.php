<?php
/**
 * «boundary» IUFormBloqueo — CU12 registrar bloqueo de agenda.   Ciclo 2 · P4 · Responsable: Jackeline
 * ingresarBloqueo(medico_id, dia_inicio, dia_fin, dia_completo, hora_inicio, hora_fin, tipo_bloqueo, motivo), presionarGuardar()
 *   -> ControlGestionarBloqueos::guardarBloqueo()
 * @var array $medicos  @var array $bloqueo  @var array $errores  @var array $citasAfectadas
 */
use App\Entidades\BloqueoAgenda;
$v = fn(string $c) => e($bloqueo[$c] ?? '');
?>
<h2>Nuevo bloqueo de agenda</h2>
<?php if (isset($errores['general'])): ?><div class="alerta error"><?= e($errores['general']) ?></div><?php endif; ?>
<?php if ($citasAfectadas): ?>
  <div class="alerta aviso">Hay <?= count($citasAfectadas) ?> cita(s) activa(s) en ese periodo. Las citas no se cancelan solas:
    deberá reprogramarlas. Presione "Guardar" de nuevo para confirmar el bloqueo.</div>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Cita</th><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Teléfono</th></tr></thead>
    <tbody>
    <?php foreach ($citasAfectadas as $c): ?>
      <tr><td><?= e($c['codigo']) ?></td><td><?= fecha($c['fecha']) ?></td><td><?= hora($c['hora_inicio']) ?></td>
          <td><?= e($c['paciente']) ?></td><td><?= e($c['telefono'] ?: '—') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
<form method="post" action="?r=bloqueos/guardar" class="formulario">
  <?= campoCsrf() ?>
  <?php if ($citasAfectadas): ?><input type="hidden" name="confirmar" value="1"><?php endif; ?>
  <?php if ($medicos): ?>
    <label><span class="obligatorio">Médico</span>
      <select name="medico_id" required><option value="">Seleccione</option>
        <?php foreach ($medicos as $m): ?>
          <option value="<?= (int) $m['persona_id'] ?>" <?= ($bloqueo['medico_id'] ?? null) == $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select><?= errorCampo($errores, 'medico_id') ?>
    </label>
  <?php else: ?>
    <input type="hidden" name="medico_id" value="<?= $v('medico_id') ?>">
  <?php endif; ?>
  <div class="fila">
    <label><span class="obligatorio">Tipo</span>
      <select name="tipo_bloqueo" required>
        <?php foreach (BloqueoAgenda::TIPOS as $k => $t): ?>
          <option value="<?= $k ?>" <?= ($bloqueo['tipo_bloqueo'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option>
        <?php endforeach; ?>
      </select><?= errorCampo($errores, 'tipo_bloqueo') ?>
    </label>
    <label>Motivo<input name="motivo" maxlength="200" value="<?= $v('motivo') ?>" placeholder="Congreso, trámite personal…"><?= errorCampo($errores, 'motivo') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">Desde el día</span><input type="date" name="dia_inicio" min="<?= date('Y-m-d') ?>" value="<?= $v('dia_inicio') ?>" required><?= errorCampo($errores, 'dia_inicio') ?></label>
    <label><span class="obligatorio">Hasta el día</span><input type="date" name="dia_fin" min="<?= date('Y-m-d') ?>" value="<?= $v('dia_fin') ?>" required><?= errorCampo($errores, 'dia_fin') ?></label>
  </div>
  <label class="sin-salto"><input type="checkbox" name="dia_completo" value="1" <?= !empty($bloqueo['dia_completo']) ? 'checked' : '' ?>> Días completos</label>
  <div class="fila">
    <label>Hora de inicio (primer día)<input type="time" name="hora_inicio" value="<?= $v('hora_inicio') ?>"><?= errorCampo($errores, 'hora_inicio') ?></label>
    <label>Hora de fin (último día)<input type="time" name="hora_fin" value="<?= $v('hora_fin') ?>"></label>
  </div>
  <div class="botones"><a class="btn secundario" href="?r=bloqueos">Cancelar</a><button class="btn">Guardar</button></div>
</form>
