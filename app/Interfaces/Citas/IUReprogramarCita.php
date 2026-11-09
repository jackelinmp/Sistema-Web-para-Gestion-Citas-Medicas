<?php
/**
 * «boundary» IUReprogramarCita — CU16 Reprogramar y cancelar cita.
 * elegirFecha(fecha, medico_id)        -> ControlReprogramarCancelarCita::mostrarCita()   («include» CU13: cupos libres)
 * elegirCupo(medico_id, hora_inicio)   -> ControlReprogramarCancelarCita::mostrarCita()
 * confirmarReprogramacion()            -> ControlReprogramarCancelarCita::reprogramarCita()
 * cancelarCita(motivo)                 -> ControlReprogramarCancelarCita::cancelarCita()
 * @var array $cita  @var array $medicos  @var string $fecha  @var int|null $medicoId  @var string $horaInicio
 * @var array|null $cupos  @var array|null $nuevoMedico
 * @var string|null $bloqueo  motivo por el que la cita ya no se puede cambiar (estado, hora o plazo)
 * @var string|null $limite   el paciente llegó al tope de reprogramaciones (todavía puede cancelar)
 * @var string|null $error    fecha elegida no válida
 */
$base = ['r' => 'citas/cambiar', 'id' => $cita['id'], 'fecha' => $fecha];
$enlace = fn(array $extra) => '?' . e(http_build_query($base + $extra));
?>
<div class="encabezado">
  <h2>Cita <?= e($cita['codigo']) ?></h2>
  <?= insignia($cita['estado']) ?>
</div>
<?php foreach ([$bloqueo, $error] as $mensaje): if ($mensaje): ?><div class="alerta error"><?= e($mensaje) ?></div><?php endif; endforeach; ?>

<div class="tarjeta">
  <dl class="resumen">
    <dt>Paciente</dt><dd><?= e($cita['paciente']) ?> (<?= e($cita['nro_expediente']) ?>)</dd>
    <dt>Especialidad</dt><dd><?= e($cita['especialidad']) ?></dd>
    <dt>Médico</dt><dd><?= e($cita['medico']) ?></dd>
    <dt>Fecha y hora actual</dt><dd><?= fecha($cita['fecha']) ?> · <?= hora($cita['hora_inicio']) ?> – <?= hora($cita['hora_fin']) ?></dd>
    <dt>Pago</dt><dd><?= insignia($cita['pago_estado']) ?></dd>
    <?php if ((int) $cita['veces_reprogramada'] > 0): ?><dt>Reprogramaciones</dt><dd><?= (int) $cita['veces_reprogramada'] ?></dd><?php endif; ?>
  </dl>
</div>

<?php if (!$bloqueo): ?>
  <h3>Reprogramar</h3>
  <?php if ($limite): ?>
  <div class="alerta aviso"><?= e($limite) ?></div>
  <?php else: ?>
  <p class="suave">Elija una fecha y, si quiere, otro médico de <?= e($cita['especialidad']) ?>. La cita conserva su código y su pago.</p>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="citas/cambiar">
    <input type="hidden" name="id" value="<?= (int) $cita['id'] ?>">
    <label>Nueva fecha<input type="date" name="fecha" min="<?= date('Y-m-d') ?>" value="<?= e($fecha) ?>" required></label>
    <label>Médico
      <select name="medico_id"><option value="">Todos</option>
        <?php foreach ($medicos as $m): ?>
          <option value="<?= (int) $m['persona_id'] ?>" <?= $medicoId === (int) $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn secundario">Ver horarios</button>
  </form>

  <?php if ($cupos !== null && !$nuevoMedico): ?>
    <?php foreach ($cupos as $fila): ?>
      <h4><?= e($fila['medico']['nombre']) ?></h4>
      <?php if (!$fila['cupos']): ?>
        <p class="suave">Sin cupos libres el <?= fecha($fecha) ?>.</p>
      <?php else: ?>
        <div class="cupos">
          <?php foreach ($fila['cupos'] as $c): ?>
            <a class="cupo" href="<?= $enlace(['medico_id' => $fila['medico']['persona_id'], 'hora_inicio' => hora($c['hora_inicio'])]) ?>"><?= hora($c['hora_inicio']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$cupos): ?><p class="suave">No hay médicos activos para esta especialidad.</p><?php endif; ?>
  <?php endif; ?>

  <?php if ($nuevoMedico): ?>
    <div class="tarjeta">
      <h4>Nuevo horario</h4>
      <dl class="resumen">
        <dt>Médico</dt><dd><?= e($nuevoMedico['nombre']) ?></dd>
        <dt>Fecha</dt><dd><?= fecha($fecha) ?></dd>
        <dt>Hora</dt><dd><?= e($horaInicio) ?></dd>
      </dl>
      <form method="post" action="?r=citas/reprogramar" class="botones">
        <?= campoCsrf() ?>
        <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
        <input type="hidden" name="medico_id" value="<?= (int) $nuevoMedico['persona_id'] ?>">
        <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
        <input type="hidden" name="hora_inicio" value="<?= e($horaInicio) ?>">
        <a class="btn secundario" href="<?= $enlace(['medico_id' => $medicoId]) ?>">Elegir otro horario</a>
        <button class="btn">Confirmar reprogramación</button>
      </form>
    </div>
  <?php endif; ?>
  <?php endif; ?>

  <h3>Cancelar la cita</h3>
  <form method="post" action="?r=citas/cancelar" class="formulario" data-confirmar="¿Cancelar la cita <?= e($cita['codigo']) ?>? El horario quedará libre para otro paciente.<?= $cita['pago_estado'] === 'PAGADO' ? ' El pago no se devuelve.' : '' ?>">
    <?= campoCsrf() ?>
    <input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
    <label><span class="obligatorio">Motivo de la cancelación</span>
      <textarea name="motivo" maxlength="200" rows="2" required minlength="5"></textarea>
    </label>
    <?php if ($cita['pago_estado'] === 'PAGADO'): ?>
      <div class="alerta aviso">La cita ya está pagada: si la cancela, el pago no se devuelve (política de la clínica). Si solo necesita otro horario, reprográmela.</div>
    <?php endif; ?>
    <div class="botones">
      <a class="btn secundario" href="?r=citas/proximas">Volver</a>
      <button class="btn peligro">Cancelar cita</button>
    </div>
  </form>
<?php else: ?>
  <div class="botones"><a class="btn secundario" href="?r=citas/proximas">Volver</a></div>
<?php endif; ?>
