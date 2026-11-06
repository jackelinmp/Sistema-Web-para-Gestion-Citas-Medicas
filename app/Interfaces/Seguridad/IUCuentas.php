<?php
/**
 * «boundary» IUCuentas — CU02 Gestionar Cuentas de Usuario (lista).   Responsable: Erika
 * solicitarModuloCuentas() / aplicarFiltros() -> ControlGestionarCuentas::listarCuentas()
 * seleccionarNuevaCuenta() / seleccionarModificar(id) -> ControlGestionarCuentas::mostrarFormulario()
 * cambiarEstado(id) -> ControlGestionarCuentas::cambiarEstado()
 * @var array $cuentas  @var array $roles  @var array $filtros
 */
use App\Nucleo\Sesion;
?>
<div class="encabezado">
  <h2>Usuarios</h2>
  <?php if (Sesion::puede('SEGURIDAD', 'R')): ?><a class="btn" href="?r=cuentas/formulario">Nueva cuenta</a><?php endif; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="cuentas">
  <label>Buscar<input name="criterio" value="<?= e($filtros['criterio']) ?>" placeholder="Nombre, CI o correo"></label>
  <label>Rol
    <select name="rol_id"><option value="">Todos</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['id'] ?>" <?= $filtros['rol_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Estado
    <select name="estado"><option value="">Todos</option>
      <?php foreach (['HABILITADA' => 'Habilitada', 'SUSPENDIDA' => 'Suspendida'] as $k => $t): ?>
        <option value="<?= $k ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Filtrar</button>
</form>
<table class="tabla">
  <thead><tr><th>Nombre</th><th>CI</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($cuentas as $c): ?>
    <tr class="<?= $c['estado'] === 'HABILITADA' ? '' : 'inactivo' ?>">
      <td><?= e($c['nombre']) ?></td><td><?= e($c['ci']) ?></td><td><?= e($c['correo']) ?></td>
      <td><?= e($c['rol']) ?></td><td><?= insignia($c['estado']) ?></td><td><?= fechaHora($c['ultimo_acceso']) ?></td>
      <td class="acciones">
        <?php if (Sesion::puede('SEGURIDAD', 'M')): ?><a href="?r=cuentas/formulario&id=<?= (int) $c['id'] ?>">Modificar</a><?php endif; ?>
        <?php if (Sesion::puede('SEGURIDAD', 'A')): ?>
          <form method="post" action="?r=cuentas/estado" data-confirmar="¿Cambiar el estado de la cuenta de <?= e($c['nombre']) ?>?">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn-enlace <?= $c['estado'] === 'HABILITADA' ? 'peligro' : '' ?>"><?= $c['estado'] === 'HABILITADA' ? 'Suspender' : 'Habilitar' ?></button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$cuentas): ?><tr><td colspan="7" class="vacio">No se encontraron cuentas.</td></tr><?php endif; ?>
  </tbody>
</table>
