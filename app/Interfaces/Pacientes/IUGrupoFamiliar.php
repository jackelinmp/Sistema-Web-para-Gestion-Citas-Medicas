<?php
/**
 * «boundary» IUGrupoFamiliar — CU22 Gestionar Grupo Familiar.   Ciclo 2 · P2 · Responsable: Jackeline
 * solicitarGrupo(paciente_id)                       -> ControlGestionarGrupoFamiliar::mostrarGrupo()
 * ingresarFamiliar(ci_familiar, parentesco)         -> ControlGestionarGrupoFamiliar::agregarFamiliar()
 * quitarFamiliar(id)                                -> ControlGestionarGrupoFamiliar::quitarFamiliar()
 * @var array $titular  @var array $familiares  @var array $titulares  @var array $errores  @var bool $puedeModificar
 */
use App\Entidades\VinculoFamiliar;
use App\Nucleo\Sesion;

$usuarioActual = Sesion::usuario();
$esCuentaPaciente = ($usuarioActual['rol'] ?? '') === 'PACIENTE';
$propioId = (int) ($usuarioActual['persona_id'] ?? 0);
$esMenor = fn($fecha) => $fecha && (new DateTime((string) $fecha))->diff(new DateTime())->y < 18;
?>
<div class="encabezado">
  <h2>Grupo familiar · <?= e($titular['nombres'] . ' ' . $titular['apellidos']) ?><?= $titular['nro_expediente'] ? ' (' . e($titular['nro_expediente']) . ')' : '' ?></h2>
  <?php if ($titular['nro_expediente']): ?><a class="btn secundario" href="?r=historial&paciente_id=<?= (int) $titular['persona_id'] ?>">Volver al historial</a><?php endif; ?>
</div>
<p class="suave">El responsable saca las citas, paga y recibe los avisos de las personas de su grupo.
  El historial y los resultados de laboratorio los ve solo de los menores de edad; un adulto los ve con su propia cuenta.</p>
<?php if ($esCuentaPaciente): ?><p><a class="btn secundario" href="?r=citas/otra-persona">+ Cita para otra persona</a></p><?php endif; ?>
<?php if (isset($errores['general'])): ?><div class="alerta error"><?= e($errores['general']) ?></div><?php endif; ?>

<table class="tabla">
  <thead><tr><th>Familiar</th><th>Parentesco</th><th>CI</th><th>Nacimiento</th><th>Estado</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php foreach ($familiares as $f): ?>
    <tr class="<?= $f['estado'] === 'ACTIVO' ? '' : 'inactivo' ?>">
      <td><?= e($f['nombre']) ?> (<?= e($f['nro_expediente']) ?>)</td>
      <td><?= e(VinculoFamiliar::PARENTESCOS[$f['parentesco']] ?? $f['parentesco']) ?></td>
      <td><?= e($f['ci']) ?></td><td><?= fecha($f['fecha_nacimiento']) ?></td><td><?= insignia($f['estado']) ?></td>
      <td class="acciones">
        <?php if (!$esCuentaPaciente || $esMenor($f['fecha_nacimiento'])): ?><a href="?r=historial&paciente_id=<?= (int) $f['familiar_id'] ?>">Historial</a><?php endif; ?>
        <?php if (Sesion::puede('CITAS', 'R') && $f['estado'] === 'ACTIVO'): ?>
          <a href="?r=citas/nueva&paciente_id=<?= (int) $f['familiar_id'] ?>">Agendar cita</a>
        <?php endif; ?>
        <?php if ($puedeModificar): ?>
          <form method="post" action="?r=grupo-familiar/quitar" data-confirmar="¿Quitar a <?= e($f['nombre']) ?> del grupo familiar?">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <button class="btn-enlace peligro">Quitar</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$familiares): ?><tr><td colspan="6" class="vacio">Este paciente no tiene familiares a cargo.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php if ($titulares): ?>
  <p>Este paciente está a cargo de:
    <?php foreach ($titulares as $i => $t): ?><?= $i ? ', ' : '' ?><?php if (!$esCuentaPaciente || (int) $t['titular_id'] === $propioId): ?><a href="?r=grupo-familiar&paciente_id=<?= (int) $t['titular_id'] ?>"><?= e($t['nombre']) ?></a><?php else: ?><?= e($t['nombre']) ?><?php endif; ?><?php endforeach; ?>
  </p>
<?php endif; ?>

<?php if ($puedeModificar && $titular['estado'] === 'ACTIVO'): ?>
  <h3>Agregar familiar</h3>
  <form method="post" action="?r=grupo-familiar/agregar" class="formulario">
    <?= campoCsrf() ?>
    <input type="hidden" name="titular_id" value="<?= (int) $titular['persona_id'] ?>">
    <div class="fila">
      <label><span class="obligatorio">CI del familiar</span><input name="ci_familiar" maxlength="15" value="<?= e($_POST['ci_familiar'] ?? '') ?>" required><?= errorCampo($errores, 'ci_familiar') ?></label>
      <label><span class="obligatorio">Parentesco</span>
        <select name="parentesco" required><option value="">Seleccione</option>
          <?php foreach (VinculoFamiliar::PARENTESCOS as $k => $t): ?><option value="<?= $k ?>" <?= ($_POST['parentesco'] ?? '') === $k ? 'selected' : '' ?>><?= e($t) ?></option><?php endforeach; ?>
        </select><?= errorCampo($errores, 'parentesco') ?></label>
    </div>
    <p class="botones"><button class="btn">Agregar</button></p>
  </form>
<?php endif; ?>
