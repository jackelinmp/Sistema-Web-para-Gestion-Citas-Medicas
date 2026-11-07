<?php
/**
 * «boundary» IUNotificaciones — CU24 Notificaciones y recordatorios.
 * listarNotificaciones() -> ControlGestionarNotificaciones::listarNotificaciones()
 * marcarLeida(id)       -> ControlGestionarNotificaciones::marcarLeida()
 * enviarRecordatorios()  -> ControlGestionarNotificaciones::enviarRecordatorios()
 * @var array $notificaciones  @var int $noLeidas  @var array $recientes  @var int $sinEnviar  @var bool $esStaff
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2>Notificaciones</h2>
  <?php if ($esStaff): ?>
    <form method="post" action="?r=notificaciones/recordatorios" style="display:inline">
      <?= campoCsrf() ?>
      <button class="btn secundario">Enviar recordatorios de mañana</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($noLeidas > 0): ?>
  <div class="alerta info">Tiene <?= (int) $noLeidas ?> aviso(s) sin leer.</div>
<?php endif; ?>

<h3>Sus avisos</h3>
<table class="tabla">
  <thead><tr><th>Tipo</th><th>Asunto</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($notificaciones as $n): ?>
    <tr>
      <td><?= e($n['tipo']) ?></td>
      <td><?= e($n['asunto']) ?></td>
      <td><?= e($n['mensaje']) ?></td>
      <td><?= fechaHora($n['fecha_creacion']) ?></td>
      <td><?= $n['leida'] ? 'Leída' : 'Nueva' ?></td>
      <td>
        <?php if (!$n['leida']): ?>
          <form method="post" action="?r=notificaciones/leida">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
            <button class="btn-enlace">Marcar leída</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$notificaciones): ?>
    <tr><td colspan="6" class="vacio">No tiene notificaciones.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<?php if ($esStaff && $recientes): ?>
  <h3>Avisos recientes (personal)</h3>
  <p class="suave">Correos pendientes de envío: <?= (int) $sinEnviar ?></p>
  <table class="tabla">
    <thead><tr><th>Destinatario</th><th>Correo</th><th>Tipo</th><th>Asunto</th><th>Enviado</th></tr></thead>
    <tbody>
    <?php foreach ($recientes as $r): ?>
      <tr>
        <td><?= e($r['destinatario']) ?></td>
        <td><?= e($r['correo']) ?></td>
        <td><?= e($r['tipo']) ?></td>
        <td><?= e($r['asunto']) ?></td>
        <td><?= $r['fecha_envio'] ? fechaHora($r['fecha_envio']) : 'Pendiente' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
