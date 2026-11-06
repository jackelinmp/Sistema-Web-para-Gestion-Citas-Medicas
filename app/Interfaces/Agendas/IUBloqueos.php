<?php
/**
 * «boundary» IUBloqueos — CU12 Gestionar Bloqueos de Agenda (lista).   Ciclo 2 · P4 · Responsable: Jackeline
 * seleccionarMedico(medico_id)  -> ControlGestionarBloqueos::listarBloqueos()
 * presionarNuevoBloqueo()       -> ControlGestionarBloqueos::mostrarFormulario()
 * quitarBloqueo(id)             -> ControlGestionarBloqueos::quitarBloqueo()
 * @var array $medicos  @var int|null $medicoId  @var array $bloqueos
 */
use App\Entidades\BloqueoAgenda;
use App\Nucleo\Sesion;
$puedeModificar = Sesion::puede('AGENDAS', 'M');
?>
<div class="encabezado">
  <h2>Bloqueos de agenda</h2>
  <?php if ($puedeModificar): ?>
    <a class="btn" href="?r=bloqueos/formulario<?= $medicoId ? '&amp;medico_id=' . (int) $medicoId : '' ?>">Nuevo bloqueo</a>
  <?php endif; ?>
</div>
<p class="suave">Vacaciones, permisos y reuniones: en esos horarios el médico no recibe citas.</p>
<?php if ($medicos): ?>
  <form class="filtros" method="get">
    <input type="hidden" name="r" value="bloqueos">
    <label>Médico
      <select name="medico_id"><option value="">Todos</option>
        <?php foreach ($medicos as $m): ?>
          <option value="<?= (int) $m['persona_id'] ?>" <?= $medicoId == $m['persona_id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn secundario">Ver</button>
  </form>
<?php endif; ?>
<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Médico</th><th>Desde</th><th>Hasta</th><th>Tipo</th><th>Motivo</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($bloqueos as $b): ?>
    <tr>
      <td><?= e($b['medico']) ?></td>
      <td class="sin-salto"><?= fechaHora($b['fecha_inicio']) ?></td><td class="sin-salto"><?= fechaHora($b['fecha_fin']) ?></td>
      <td><?= e(BloqueoAgenda::TIPOS[$b['tipo_bloqueo']] ?? $b['tipo_bloqueo']) ?></td>
      <td><?= e($b['motivo'] ?? '—') ?></td>
      <td class="acciones">
        <?php if ($puedeModificar): ?>
          <form method="post" action="?r=bloqueos/quitar" data-confirmar="¿Quitar este bloqueo? Los horarios volverán a estar disponibles.">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <button class="btn-enlace peligro">Quitar</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$bloqueos): ?><tr><td colspan="6" class="vacio">No hay bloqueos vigentes ni próximos.</td></tr><?php endif; ?>
  </tbody>
</table></div>
