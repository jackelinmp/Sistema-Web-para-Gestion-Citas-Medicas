<?php
/**
 * «boundary» IUJornadas — CU11 Configurar Jornada de Atención (RF05).   Responsable: Jackeline
 * seleccionarMedico(medico_id) -> ControlConfigurarJornada::mostrarJornada()
 * ingresarJornada(dias, hora_inicio, hora_fin, duracion_bloque_min), presionarGuardar() -> ControlConfigurarJornada::guardarJornada()
 * @var array $medicos  @var int|null $medicoId  @var array $jornada  @var array $errores  @var int $pedirConfirmacion
 */
use App\Controles\Agendas\ControlConfigurarJornada;
use App\Nucleo\Sesion;
?>
<h2>Jornada de atención</h2>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="jornadas">
  <label>Médico
    <select name="medico_id" required><option value="">Seleccione un médico</option>
      <?php foreach ($medicos as $m): ?>
        <option value="<?= (int) $m['persona_id'] ?>" <?= $medicoId == $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Ver jornada</button>
</form>

<?php if ($medicoId): ?>
  <?php foreach ($errores as $err): ?><div class="alerta error"><?= e($err) ?></div><?php endforeach; ?>
  <?php if ($pedirConfirmacion): ?>
    <div class="alerta aviso">El nuevo horario deja fuera <?= (int) $pedirConfirmacion ?> cita(s) ya programada(s). Revise y presione "Guardar" de nuevo para confirmar.</div>
  <?php endif; ?>
  <form method="post" action="?r=jornadas/guardar">
    <?= campoCsrf() ?>
    <input type="hidden" name="medico_id" value="<?= (int) $medicoId ?>">
    <?php if ($pedirConfirmacion): ?><input type="hidden" name="confirmar" value="1"><?php endif; ?>
    <table class="tabla">
      <thead><tr><th>Día</th><th>Atiende</th><th>Hora de ingreso</th><th>Hora de salida</th><th>Duración de la cita (min)</th></tr></thead>
      <tbody>
      <?php foreach (ControlConfigurarJornada::DIAS as $n => $dia): $j = $jornada[$n] ?? null; ?>
        <tr>
          <td><?= $dia ?></td>
          <td><input type="checkbox" name="atiende[<?= $n ?>]" value="1" <?= $j ? 'checked' : '' ?>></td>
          <td><input type="time" name="hora_inicio[<?= $n ?>]" value="<?= hora($j['hora_inicio'] ?? '08:00') ?>"></td>
          <td><input type="time" name="hora_fin[<?= $n ?>]" value="<?= hora($j['hora_fin'] ?? '12:00') ?>"></td>
          <td><input type="number" name="duracion_bloque_min[<?= $n ?>]" min="20" max="45" step="5" value="<?= (int) ($j['duracion_bloque_min'] ?? 30) ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (Sesion::puede('AGENDAS', 'M')): ?>
      <p class="botones"><button class="btn">Guardar</button></p>
    <?php endif; ?>
  </form>
<?php endif; ?>
