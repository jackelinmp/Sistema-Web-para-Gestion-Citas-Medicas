<?php
/**
 * «boundary» IUPacientes — CU05 Gestionar Pacientes (lista y búsqueda, RF02).   Responsable: Erika
 * solicitarModuloPacientes() / buscarPaciente(criterio) -> ControlGestionarPacientes::listarPacientes()
 * seleccionarNuevoPaciente() / seleccionarModificar(id) -> ControlGestionarPacientes::mostrarFormulario()
 * cambiarEstado(id) -> ControlGestionarPacientes::cambiarEstado()
 * @var array $pacientes  @var string $criterio  @var string $estado
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2>Pacientes</h2>
  <?php if (Sesion::puede('PACIENTES', 'R')): ?><a class="btn" href="?r=pacientes/formulario">Nuevo paciente</a><?php endif; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="pacientes">
  <label>Buscar<input name="criterio" value="<?= e($criterio) ?>" placeholder="CI, nombre, apellido o expediente"></label>
  <label>Estado
    <select name="estado"><option value="">Todos</option>
      <option value="ACTIVO" <?= $estado === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
      <option value="INACTIVO" <?= $estado === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
    </select>
  </label>
  <button class="btn secundario">Buscar</button>
</form>
<table class="tabla">
  <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th>Nacimiento</th><th>Teléfono</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($pacientes as $p): ?>
    <tr class="<?= $p['estado'] === 'ACTIVO' ? '' : 'inactivo' ?>">
      <td><?= e($p['nro_expediente']) ?></td>
      <td><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></td>
      <td><?= e($p['ci']) ?></td>
      <td><?= fecha($p['fecha_nacimiento']) ?></td>
      <td><?= e($p['telefono']) ?></td>
      <td><?= insignia($p['estado']) ?></td>
      <td class="acciones">
        <?php if (Sesion::puede('PACIENTES', 'M')): ?>
          <a href="?r=pacientes/formulario&id=<?= (int) $p['persona_id'] ?>">Modificar</a>
          <form method="post" action="?r=pacientes/estado" data-confirmar="¿Cambiar el estado de <?= e($p['nombres'] . ' ' . $p['apellidos']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="persona_id" value="<?= (int) $p['persona_id'] ?>">
            <button class="btn-enlace <?= $p['estado'] === 'ACTIVO' ? 'peligro' : '' ?>"><?= $p['estado'] === 'ACTIVO' ? 'Desactivar' : 'Reactivar' ?></button>
          </form>
        <?php endif; ?>
        <?php if (Sesion::puede('CITAS', 'R') && $p['estado'] === 'ACTIVO'): ?>
          <a href="?r=citas/nueva&paciente_id=<?= (int) $p['persona_id'] ?>">Agendar cita</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pacientes): ?><tr><td colspan="7" class="vacio">No se encontraron pacientes.</td></tr><?php endif; ?>
  </tbody>
</table>
