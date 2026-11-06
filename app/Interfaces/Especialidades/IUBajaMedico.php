<?php
/**
 * «boundary» IUBajaMedico — CU10 Dar de Baja a un Médico.   Ciclo 2 · Responsable: Jackeline
 * buscarMedico(criterio) / seleccionarMedico(id) -> ControlDarBajaMedico::mostrarBaja()
 * ingresarMotivo(motivo), confirmar()  -> ControlDarBajaMedico::confirmarBaja()
 * presionarReactivar(id)               -> ControlDarBajaMedico::reactivarMedico()
 * @var array|null $medico  (paso 1: @var string $criterio  @var array $medicos)  (paso 2: @var array $pendientes  @var array $errores)
 */
if (!$medico): ?>
<h2>Dar de baja a médico</h2>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="bajas">
  <label>Buscar<input name="criterio" value="<?= e($criterio) ?>" placeholder="Nombre, CI o matrícula"></label>
  <button class="btn secundario">Buscar</button>
</form>
<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Médico</th><th>Matrícula</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($medicos as $m): ?>
    <tr class="<?= $m['estado'] === 'ACTIVO' ? '' : 'inactivo' ?>">
      <td><?= e($m['nombre'] ?? ($m['nombres'] . ' ' . $m['apellidos'])) ?></td><td><?= e($m['matricula']) ?></td><td><?= insignia($m['estado']) ?></td>
      <td class="acciones"><a class="btn pequeno <?= $m['estado'] === 'ACTIVO' ? 'peligro' : 'secundario' ?>" href="?r=bajas&amp;id=<?= (int) $m['persona_id'] ?>"><?= $m['estado'] === 'ACTIVO' ? 'Dar de baja' : 'Reactivar' ?></a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$medicos): ?><tr><td colspan="4" class="vacio">No se encontraron médicos.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php return; endif;
$activo = $medico['estado'] === 'ACTIVO';
?>
<div class="encabezado">
  <h2><?= $activo ? 'Dar de baja al médico' : 'Médico dado de baja' ?></h2>
  <a class="btn secundario" href="?r=bajas">Volver</a>
</div>
<?php if (isset($errores['general'])): ?><div class="alerta error"><?= e($errores['general']) ?></div><?php endif; ?>
<div class="tarjeta">
  <dl class="resumen">
    <dt>Médico</dt><dd><?= e($medico['nombre']) ?></dd>
    <dt>Matrícula</dt><dd><?= e($medico['matricula']) ?></dd>
    <dt>Correo</dt><dd><?= e($medico['correo'] ?: '—') ?></dd>
    <dt>Estado</dt><dd><?= insignia($medico['estado']) ?></dd>
  </dl>

  <?php if ($activo && $pendientes): ?>
    <div class="alerta aviso">Tiene <?= count($pendientes) ?> cita(s) pendiente(s). Debe reprogramarlas o cancelarlas antes de darlo de baja.</div>
    <table class="tabla">
      <thead><tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Teléfono</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($pendientes as $c): ?>
        <tr><td><?= e($c['codigo']) ?></td><td><?= fecha($c['fecha']) ?></td><td><?= hora($c['hora_inicio']) ?></td>
            <td><?= e($c['paciente']) ?></td><td><?= e($c['telefono'] ?: '—') ?></td><td><?= insignia($c['estado']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif ($activo): ?>
    <p>Al darlo de baja: deja de aparecer para agendar citas, su jornada de atención se cierra y su cuenta de usuario se suspende.
       Su historial de citas se conserva.</p>
    <form method="post" action="?r=bajas/confirmar" class="formulario" data-confirmar="¿Confirma la baja de <?= e($medico['nombre']) ?>?">
      <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $medico['persona_id'] ?>">
      <label><span class="obligatorio">Motivo de la baja</span>
        <textarea name="motivo" rows="2" maxlength="250" required><?= e($_POST['motivo'] ?? '') ?></textarea><?= errorCampo($errores, 'motivo') ?>
      </label>
      <div class="botones"><button class="btn peligro">Dar de baja</button></div>
    </form>
  <?php else: ?>
    <form method="post" action="?r=bajas/reactivar" data-confirmar="¿Reactivar a <?= e($medico['nombre']) ?>?">
      <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $medico['persona_id'] ?>">
      <div class="botones"><button class="btn">Reactivar médico</button></div>
    </form>
  <?php endif; ?>
</div>
