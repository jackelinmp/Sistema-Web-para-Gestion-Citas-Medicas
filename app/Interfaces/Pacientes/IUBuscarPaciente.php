<?php
/**
 * «boundary» parcial IUBuscarPaciente — paso "elegir paciente" compartido por CU07, CU21, CU22 y CU23.
 * Se incluye con require dentro de otra interfaz.
 * buscarPaciente(criterio) -> la operación de la ruta $rutaBusqueda
 * @var string $rutaBusqueda  @var string $criterio  @var array $pacientes  (opcional @var string $titulo si se muestra sola)
 */
?>
<?php if (!empty($titulo)): ?><h2><?= e($titulo) ?></h2><?php endif; ?>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="<?= e($rutaBusqueda) ?>">
  <label>Buscar paciente<input name="criterio" value="<?= e($criterio) ?>" placeholder="CI, nombre o expediente" required autofocus></label>
  <button class="btn">Buscar</button>
</form>
<?php if ($criterio !== ''): ?>
  <table class="tabla">
    <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th>Nacimiento</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pacientes as $pa): ?>
      <tr class="<?= $pa['estado'] === 'ACTIVO' ? '' : 'inactivo' ?>">
        <td><?= e($pa['nro_expediente']) ?></td><td><?= e($pa['apellidos'] . ', ' . $pa['nombres']) ?></td>
        <td><?= e($pa['ci']) ?></td><td><?= fecha($pa['fecha_nacimiento']) ?></td><td><?= insignia($pa['estado']) ?></td>
        <td class="acciones"><a class="btn pequeno" href="?r=<?= e($rutaBusqueda) ?>&paciente_id=<?= (int) $pa['persona_id'] ?>">Seleccionar</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pacientes): ?><tr><td colspan="6" class="vacio">No se encontró un paciente con los datos ingresados.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
