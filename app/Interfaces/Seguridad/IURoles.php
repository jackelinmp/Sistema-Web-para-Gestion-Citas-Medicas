<?php
/**
 * «boundary» IURoles — CU03 Gestionar Roles y Permisos.   Ciclo 1 · P1
 * seleccionarRol(id) -> ControlGestionarRoles::listarRoles()
 * guardarPermisos(rol_id, permiso[]) -> ControlGestionarRoles::guardarPermisos()
 * @var array $roles  @var array $seleccionado  @var array $matriz  [modulo => [accion => [id, tiene]]]
 */
$acciones = ['C' => 'Consultar', 'R' => 'Registrar', 'M' => 'Modificar', 'A' => 'Anular'];
$nombres = ['ESPECIALIDADES_MEDICOS' => 'Especialidades y médicos', 'COMUNICACION' => 'Comunicación', 'EXAMENES' => 'Exámenes', 'AGENDAS' => 'Agendas', 'CITAS' => 'Citas', 'PACIENTES' => 'Pacientes', 'PAGOS' => 'Pagos', 'REPORTES' => 'Reportes', 'SEGURIDAD' => 'Seguridad'];
$protegido = $seleccionado['nombre'] === 'ADMINISTRADOR';
?>
<div class="encabezado">
  <h2>Roles y permisos</h2>
  <span class="suave">Marque qué puede hacer cada rol en cada módulo</span>
</div>
<nav class="pestanas-ciclo" aria-label="Roles">
  <?php foreach ($roles as $r): ?>
    <a href="?r=roles&amp;rol_id=<?= (int) $r['id'] ?>" class="<?= (int) $r['id'] === (int) $seleccionado['id'] ? 'activa' : '' ?>">
      <?= e($r['nombre']) ?><small><?= (int) $r['usuarios'] ?> usuarios</small>
    </a>
  <?php endforeach; ?>
</nav>
<form method="post" action="?r=roles/guardar">
  <?= campoCsrf() ?>
  <input type="hidden" name="rol_id" value="<?= (int) $seleccionado['id'] ?>">
  <div class="tabla-contenedor">
  <table class="tabla matriz">
    <thead><tr><th>Módulo</th><?php foreach ($acciones as $t): ?><th class="centro"><?= $t ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($matriz as $modulo => $fila): ?>
      <tr>
        <td><strong><?= e($nombres[$modulo] ?? $modulo) ?></strong></td>
        <?php foreach ($acciones as $a => $t): ?>
          <td class="centro">
            <?php if (isset($fila[$a])): $bloq = $protegido && $modulo === 'SEGURIDAD'; ?>
              <input type="checkbox" name="permiso[]" value="<?= $fila[$a]['id'] ?>" aria-label="<?= e("$modulo $t") ?>"
                     <?= $fila[$a]['tiene'] ? 'checked' : '' ?> <?= $bloq ? 'onclick="return false" title="El Administrador conserva los permisos de seguridad"' : '' ?>>
            <?php else: ?><span class="suave">—</span><?php endif; ?>
          </td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($protegido): ?><p class="suave">El rol Administrador no puede perder los permisos de seguridad.</p><?php endif; ?>
  <div class="acciones"><button class="btn">Guardar permisos</button></div>
</form>
