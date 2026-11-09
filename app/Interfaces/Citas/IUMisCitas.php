<?php
/**
 * «boundary» IUMisCitas — CU16 Reprogramar y cancelar cita: lista de citas próximas.
 * buscarCita(criterio)       -> ControlReprogramarCancelarCita::listarCitas()      (solo personal)
 * seleccionarCita(id)        -> ControlReprogramarCancelarCita::mostrarCita()
 * @var array $citas  @var string $criterio  @var bool $esPaciente
 */
use App\Nucleo\Sesion;

$mostrarPaciente = $mostrarPaciente ?? !$esPaciente;
?>
<div class="encabezado">
  <h2><?= $esPaciente ? 'Mis citas' : 'Reprogramar o cancelar citas' ?></h2>
  <?php if (Sesion::puede('CITAS', 'R')): ?><a class="btn" href="?r=citas/nueva">Nueva cita</a><?php endif; ?>
</div>
<?php if ($esPaciente): ?>
  <p class="suave">Puede reprogramar o cancelar sus citas hasta 24 horas antes. Después, comuníquese con recepción. Si cancela una cita pagada, el pago no se devuelve.</p>
<?php else: ?>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="citas/proximas">
    <label>Buscar<input name="criterio" value="<?= e($criterio) ?>" placeholder="Paciente, CI o código de cita"></label>
    <button class="btn secundario">Buscar</button>
  </form>
<?php endif; ?>

<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Fecha</th><th>Código</th><?php if ($mostrarPaciente): ?><th>Paciente</th><?php endif; ?>
    <th>Especialidad</th><th>Médico</th><th>Estado</th><th>Pago</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($citas as $c): ?>
    <tr>
      <td class="sin-salto"><?= fecha($c['fecha']) ?> <?= hora($c['hora_inicio']) ?></td>
      <td><?= e($c['codigo']) ?></td>
      <?php if ($mostrarPaciente): ?><td><?= e($c['paciente']) ?><br><small class="suave">CI <?= e($c['paciente_ci']) ?></small></td><?php endif; ?>
      <td><?= e($c['especialidad']) ?></td>
      <td><?= e($c['medico']) ?></td>
      <td><?= insignia($c['estado']) ?><?php if ((int) $c['veces_reprogramada'] > 0): ?><br><small class="suave">Reprogramada <?= (int) $c['veces_reprogramada'] === 1 ? '1 vez' : (int) $c['veces_reprogramada'] . ' veces' ?></small><?php endif; ?></td>
      <td><?= insignia($c['pago_estado']) ?></td>
      <td><a class="btn pequeno secundario" href="?r=citas/cambiar&amp;id=<?= (int) $c['id'] ?>">Reprogramar o cancelar</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$citas): ?><tr><td colspan="<?= $mostrarPaciente ? 8 : 7 ?>" class="vacio">No hay citas próximas<?= $criterio !== '' ? ' para la búsqueda' : '' ?>.</td></tr><?php endif; ?>
  </tbody>
</table></div>
