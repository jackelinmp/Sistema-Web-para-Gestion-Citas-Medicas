<?php
/**
 * «boundary» IUEspecialidades — CU08 Gestionar Especialidades (lista).
 * Operaciones (acción del usuario -> operación del control):
 *   solicitarModuloEspecialidades()  -> ControlGestionarEspecialidades::listarEspecialidades()
 *   seleccionarNuevaEspecialidad()   -> ControlGestionarEspecialidades::mostrarFormulario()
 *   seleccionarModificar(id)         -> ControlGestionarEspecialidades::mostrarFormulario(id)
 *   cambiarEstado(id)                -> ControlGestionarEspecialidades::cambiarEstado()
 * @var array $especialidades
 */
use App\Nucleo\Sesion;
$sexo = ['A' => 'Ambos', 'F' => 'Femenino', 'M' => 'Masculino'];
?>
<div class="encabezado">
  <h2>Especialidades</h2>
  <?php if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'R')): ?>
    <a class="btn" href="?r=especialidades/formulario">Nueva especialidad</a>
  <?php endif; ?>
</div>
<table class="tabla">
  <thead>
    <tr><th>Nombre</th><th class="num">Costo</th><th>Edad</th><th>Sexo</th><th>Estado</th><th>Acciones</th></tr>
  </thead>
  <tbody>
  <?php foreach ($especialidades as $esp): ?>
    <tr class="<?= $esp['estado'] ? '' : 'inactivo' ?>">
      <td><?= e($esp['nombre']) ?></td>
      <td class="num"><?= bs($esp['costo_consulta']) ?></td>
      <td><?= e($esp['edad_minima']) ?> – <?= $esp['edad_maxima'] === null ? 'sin límite' : e($esp['edad_maxima']) ?></td>
      <td><?= e($sexo[$esp['sexo_permitido']]) ?></td>
      <td><?= insignia((bool) $esp['estado']) ?></td>
      <td class="acciones">
        <?php if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'M')): ?>
          <a href="?r=especialidades/formulario&id=<?= (int) $esp['id'] ?>">Modificar</a>
        <?php endif; ?>
        <?php if (Sesion::puede('ESPECIALIDADES_MEDICOS', 'A')): ?>
          <form method="post" action="?r=especialidades/estado" data-confirmar="¿Cambiar el estado de <?= e($esp['nombre']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $esp['id'] ?>">
            <button class="btn-enlace <?= $esp['estado'] ? 'peligro' : '' ?>"><?= $esp['estado'] ? 'Desactivar' : 'Reactivar' ?></button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$especialidades): ?>
    <tr><td colspan="6" class="vacio">No hay especialidades registradas.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
