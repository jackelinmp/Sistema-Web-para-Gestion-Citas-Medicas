<?php
/**
 * «boundary» IUHistorialPaciente — CU07 Consultar Historial del Paciente.   Ciclo 2 · P8 · Responsable: Jackeline
 * buscarPaciente(criterio) / seleccionarPaciente(paciente_id) -> ControlConsultarHistorial::mostrarHistorial()
 * aplicarFiltros(desde, hasta, estado)                         -> ControlConsultarHistorial::mostrarHistorial()
 * @var array|null $paciente  (paso 1: @var string $criterio  @var array $pacientes)
 * (paso 2: @var array $ficha  @var array $citas  @var array $resumen  @var array $examenes  @var array $familiares
 *          @var array $titulares  @var array $filtros)
 */
use App\Controles\Pacientes\ControlConsultarHistorial;
use App\Nucleo\Sesion;
?>
<h2>Historial del paciente</h2>
<?php if (!$paciente): $rutaBusqueda = 'historial'; require __DIR__ . '/IUBuscarPaciente.php'; return; endif; ?>

<?php $pid = (int) $paciente['persona_id']; ?>
<div class="tarjeta">
  <div class="encabezado">
    <h3><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> · <?= e($paciente['nro_expediente']) ?></h3>
    <?= insignia($paciente['estado']) ?>
  </div>
  <dl class="resumen">
    <dt>CI</dt><dd><?= e($paciente['ci']) ?></dd>
    <dt>Nacimiento</dt><dd><?= fecha($paciente['fecha_nacimiento']) ?> (<?= (int) date_diff(date_create($paciente['fecha_nacimiento']), date_create())->y ?> años)</dd>
    <dt>Sexo</dt><dd><?= $paciente['sexo'] === 'F' ? 'Femenino' : 'Masculino' ?></dd>
    <dt>Teléfono</dt><dd><?= e($paciente['telefono'] ?: '—') ?></dd>
    <dt>Contacto de emergencia</dt><dd><?= e($paciente['contacto_emergencia_nombre'] . ' (' . $paciente['contacto_emergencia_parentesco'] . ') ' . $paciente['contacto_emergencia_telefono']) ?></dd>
  </dl>
  <div class="botones">
    <?php
    foreach (['ficha' => ['PACIENTES', 'Ficha clínica'], 'laboratorio' => ['EXAMENES', 'Laboratorio'],
              'comprobantes' => ['PAGOS', 'Pagos'], 'grupo-familiar' => ['PACIENTES', 'Grupo familiar']] as $ruta => [$modulo, $texto]):
      if (Sesion::puede($modulo, 'C')): ?>
        <a class="btn secundario" href="?r=<?= $ruta ?>&paciente_id=<?= $pid ?>"><?= $texto ?></a>
    <?php endif; endforeach; ?>
    <?php if (Sesion::puede('CITAS', 'R') && $paciente['estado'] === 'ACTIVO'): ?>
      <a class="btn" href="?r=citas/nueva&paciente_id=<?= $pid ?>">Agendar cita</a>
    <?php endif; ?>
  </div>
</div>

<h3>Datos clínicos básicos</h3>
<dl class="resumen">
  <dt>Tipo de sangre</dt><dd><?= e($ficha['tipo_sangre'] ?? 'No especificado') ?></dd>
  <dt>Alergias</dt><dd><?= e($ficha['alergias'] ?? 'No especificado') ?></dd>
  <dt>Enfermedades crónicas</dt><dd><?= e($ficha['enfermedades_cronicas'] ?? 'No especificado') ?></dd>
</dl>

<h3>Citas</h3>
<div class="indicadores">
  <?php foreach ($resumen as $estado => $n): ?>
    <div class="indicador"><div class="valor"><?= (int) $n ?></div><div class="rotulo"><?= e(ucfirst(strtolower($estado))) ?></div></div>
  <?php endforeach; ?>
</div>
<form class="filtros" method="get">
  <input type="hidden" name="r" value="historial"><input type="hidden" name="paciente_id" value="<?= $pid ?>">
  <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
  <label>Estado
    <select name="estado"><option value="">Todos</option>
      <?php foreach (ControlConsultarHistorial::ESTADOS_CITA as $es): ?>
        <option value="<?= $es ?>" <?= $filtros['estado'] === $es ? 'selected' : '' ?>><?= e(ucfirst(strtolower($es))) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn secundario">Filtrar</button>
</form>
<div class="tabla-contenedor">
<table class="tabla">
  <thead><tr><th>Código</th><th>Fecha</th><th>Hora</th><th>Especialidad</th><th>Médico</th><th>Motivo</th><th>Estado</th><th>Pago</th></tr></thead>
  <tbody>
  <?php foreach ($citas as $c): ?>
    <tr>
      <td><?= e($c['codigo']) ?></td><td><?= fecha($c['fecha']) ?></td><td><?= hora($c['hora_inicio']) ?></td>
      <td><?= e($c['especialidad']) ?></td><td><?= e($c['medico']) ?></td><td><?= e($c['motivo_consulta'] ?: '—') ?></td>
      <td><?= insignia($c['estado']) ?></td>
      <td><?= $c['estado_pago'] ? insignia($c['estado_pago']) . ' ' . bs($c['monto']) : '—' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$citas): ?><tr><td colspan="8" class="vacio">No hay citas para los filtros seleccionados.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h3>Exámenes de laboratorio</h3>
<table class="tabla">
  <thead><tr><th>Solicitado</th><th>Examen</th><th>Cita</th><th>Médico</th><th>Estado</th></tr></thead>
  <tbody>
  <?php foreach ($examenes as $x): ?>
    <tr><td><?= fechaHora($x['fecha_solicitud']) ?></td><td><?= e($x['examen']) ?></td><td><?= e($x['cita_codigo']) ?></td>
        <td><?= e($x['medico']) ?></td><td><?= insignia($x['estado']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$examenes): ?><tr><td colspan="5" class="vacio">Sin exámenes registrados.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php
// El paciente solo abre el historial de los menores a su cargo (los adultos ven el suyo con su propia cuenta).
$usuarioActual = \App\Nucleo\Sesion::usuario();
$esCuentaPaciente = ($usuarioActual['rol'] ?? '') === 'PACIENTE';
$propioId = (int) ($usuarioActual['persona_id'] ?? 0);
$esMenor = fn($fecha) => $fecha && (new DateTime((string) $fecha))->diff(new DateTime())->y < 18;
?>
<?php if ($familiares): ?>
  <h3>Familiares a cargo</h3>
  <table class="tabla">
    <thead><tr><th>Familiar</th><th>Expediente</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($familiares as $f): ?>
      <tr><td><?= e($f['nombre']) ?></td><td><?= e($f['nro_expediente']) ?></td>
          <td class="acciones"><?php if (!$esCuentaPaciente || $esMenor($f['fecha_nacimiento'] ?? null)): ?><a href="?r=historial&paciente_id=<?= (int) $f['familiar_id'] ?>">Ver historial</a><?php else: ?><span class="suave">Lo ve con su propia cuenta</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php if ($titulares): ?>
  <p class="suave">A cargo de:
    <?php foreach ($titulares as $i => $t): ?><?= $i ? ', ' : '' ?><?php if (!$esCuentaPaciente || (int) $t['titular_id'] === $propioId): ?><a href="?r=historial&amp;paciente_id=<?= (int) $t['titular_id'] ?>"><?= e($t['nombre']) ?></a><?php else: ?><?= e($t['nombre']) ?><?php endif; ?><?php endforeach; ?>
  </p>
<?php endif; ?>
