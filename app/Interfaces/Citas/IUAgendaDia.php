<?php
/**
 * «boundary» IUAgendaDia — CU15 Gestionar Estados de Cita y CU17 Registrar Inasistencia (RF15, RF17).
 * listaAgenda(fecha, medico_id, estado) -> ControlGestionarEstadosCita::listarAgenda()
 * confirmarCita(id)                     -> ControlGestionarEstadosCita::confirmarCita()
 * marcarAtendida(id)                    -> ControlGestionarEstadosCita::marcarAtendida()
 * marcarInasistencia(id)                -> ControlGestionarEstadosCita::marcarInasistencia()
 * reprogramarOCancelar(id)              -> ControlReprogramarCancelarCita::mostrarCita()   (CU16)
 * avisarPorWhatsApp(id)                 -> abre WhatsApp con el recordatorio escrito (respaldo manual de CU24)
 * @var array $citas  @var array $medicos  @var int|null $medicoId  @var string $fecha  @var string $estado
 */
use App\Nucleo\Sesion;
use App\Servicios\ServicioWhatsApp;

$usuario = Sesion::usuario();
$rol = (string) ($usuario['rol'] ?? '');
$puedeConfirmar = in_array($rol, ['ADMINISTRADOR', 'RECEPCIONISTA'], true);
$puedeAtender = in_array($rol, ['ADMINISTRADOR', 'MEDICO'], true);
$puedeInasistencia = in_array($rol, ['ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO'], true);
?>
<div class="encabezado">
  <h2>Agenda del día</h2>
  <?php if (Sesion::puede('CITAS', 'R')): ?><a class="btn" href="?r=citas/nueva">Nueva cita</a><?php endif; ?>
</div>

<form class="filtros" method="get">
  <input type="hidden" name="r" value="citas/agenda">
  <label>Fecha<input type="date" name="fecha" value="<?= e($fecha) ?>" required></label>
  <?php if ($medicos): ?>
    <label>Médico
      <select name="medico_id">
        <option value="">Todos</option>
        <?php foreach ($medicos as $m): ?>
          <option value="<?= (int) $m['persona_id'] ?>" <?= $medicoId === (int) $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  <?php endif; ?>
  <label>Estado
    <select name="estado">
      <option value="">Todos</option>
      <?php foreach (['PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA'] as $opcion): ?>
        <option value="<?= e($opcion) ?>" <?= $estado === $opcion ? 'selected' : '' ?>><?= e(ucfirst(strtolower($opcion))) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Ver agenda</button>
</form>

<table class="tabla">
  <thead>
    <tr><th>Hora</th><th>Código</th><th>Paciente</th><th>Médico</th><th>Especialidad</th><th>Estado</th><th>Motivo</th><th>Acciones</th></tr>
  </thead>
  <tbody>
  <?php foreach ($citas as $cita): ?>
    <?php $horaYaPaso = strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time(); ?>
    <tr>
      <td><?= hora($cita['hora_inicio']) ?> – <?= hora($cita['hora_fin']) ?></td>
      <td><?= e($cita['codigo']) ?></td>
      <td><?= e($cita['paciente']) ?></td>
      <td><?= e($cita['medico']) ?></td>
      <td><?= e($cita['especialidad']) ?></td>
      <td><?= insignia($cita['estado']) ?></td>
      <td><?= e($cita['motivo_consulta'] ?: '—') ?></td>
      <td class="acciones">
        <?php if ($cita['estado'] === 'PROGRAMADA' && $puedeConfirmar): ?>
          <form method="post" action="?r=citas/estado/confirmar" data-confirmar="¿Confirmar la cita <?= e($cita['codigo']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
            <button class="btn-enlace">Confirmar</button>
          </form>
        <?php endif; ?>

        <?php if ($cita['estado'] === 'CONFIRMADA' && $puedeAtender && $horaYaPaso): ?>
          <form method="post" action="?r=citas/estado/atender" data-confirmar="¿Marcar como atendida la cita <?= e($cita['codigo']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
            <button class="btn-enlace">Marcar atendida</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($cita['estado'], ['PROGRAMADA', 'CONFIRMADA'], true) && $puedeInasistencia && $horaYaPaso): ?>
          <form method="post" action="?r=citas/estado/inasistencia" data-confirmar="¿Registrar la inasistencia de la cita <?= e($cita['codigo']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="cita_id" value="<?= (int) $cita['id'] ?>">
            <button class="btn-enlace peligro">Inasistencia</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($cita['estado'], ['PROGRAMADA', 'CONFIRMADA'], true) && $puedeConfirmar && !$horaYaPaso): ?>
          <a class="btn-enlace" href="?r=citas/cambiar&amp;id=<?= (int) $cita['id'] ?>">Reprogramar o cancelar</a>
        <?php endif; ?>

        <?php if (in_array($cita['estado'], ['PROGRAMADA', 'CONFIRMADA'], true) && $puedeConfirmar && !$horaYaPaso
                  && ($enlaceWa = ServicioWhatsApp::enlaceChat((string) ($cita['telefono_aviso'] ?? ''),
                      'Clínica Vida Salud: le recordamos la cita de ' . $cita['paciente'] . ' el ' . date('d/m/Y', strtotime($cita['fecha']))
                      . ' a las ' . hora($cita['hora_inicio']) . ' con ' . $cita['medico'] . ' (' . $cita['especialidad'] . '). Código ' . $cita['codigo'] . '.'))): ?>
          <a class="btn-enlace" href="<?= e($enlaceWa) ?>" target="_blank" rel="noopener" title="Abre WhatsApp con el recordatorio ya escrito">Avisar por WhatsApp</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$citas): ?>
    <tr><td colspan="8" class="vacio">No hay citas para la fecha, el médico y el estado seleccionados.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
