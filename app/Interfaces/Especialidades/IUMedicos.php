<?php
/**
 * «boundary» IUMedicos — CU09 Gestionar Médicos (lista, RF03).   Responsable: Erika
 * solicitarModuloMedicos() / aplicarFiltros() -> ControlGestionarMedicos::listarMedicos()
 * seleccionarNuevoMedico() / seleccionarModificar(id) -> ControlGestionarMedicos::mostrarFormulario()
 * @var array $medicos  @var array $especialidades  @var string $criterio  @var int|null $especialidad_id
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2>Médicos</h2>
  <?php if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'R')): ?><a class="btn" href="?r=medicos/formulario">Nuevo médico</a><?php endif; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="medicos">
  <label>Buscar<input name="criterio" value="<?= e($criterio) ?>" placeholder="Nombre o matrícula"></label>
  <label>Especialidad
    <select name="especialidad_id"><option value="">Todas</option>
      <?php foreach ($especialidades as $esp): ?>
        <option value="<?= (int) $esp['id'] ?>" <?= $especialidad_id == $esp['id'] ? 'selected' : '' ?>><?= e($esp['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Filtrar</button>
</form>
<table class="tabla">
  <thead><tr><th>Médico</th><th>Matrícula</th><th>Especialidades</th><th>Teléfono</th><th>Correo</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($medicos as $m): ?>
    <tr class="<?= $m['estado'] === 'ACTIVO' ? '' : 'inactivo' ?>">
      <td><?= e($m['nombre']) ?></td><td><?= e($m['matricula']) ?></td><td><?= e($m['especialidades']) ?></td>
      <td><?= e($m['telefono']) ?></td><td><?= e($m['correo']) ?></td><td><?= insignia($m['estado']) ?></td>
      <td class="acciones">
        <?php if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'M')): ?><a href="?r=medicos/formulario&id=<?= (int) $m['persona_id'] ?>">Modificar</a><?php endif; ?>
        <?php if (Sesion::puede('AGENDAS', 'C')): ?><a href="?r=jornadas&medico_id=<?= (int) $m['persona_id'] ?>">Jornada</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$medicos): ?><tr><td colspan="7" class="vacio">No se encontraron médicos.</td></tr><?php endif; ?>
  </tbody>
</table>
