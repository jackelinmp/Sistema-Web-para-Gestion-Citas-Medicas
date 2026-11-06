<?php
/**
 * «boundary» IUResultados — CU23 Gestionar Resultados de Laboratorio (lista).   Ciclo 2 · P8 · Responsable: Jackeline
 * buscarPaciente(criterio) / seleccionarPaciente(paciente_id) -> ControlGestionarResultados::listarResultados()
 * presionarSolicitarExamen()        -> ControlGestionarResultados::mostrarSolicitud()
 * presionarEnProceso(id)            -> ControlGestionarResultados::marcarEnProceso()
 * presionarCargarResultado(id)      -> ControlGestionarResultados::mostrarFormulario()
 * presionarEntregado(id)            -> ControlGestionarResultados::marcarEntregado()
 * presionarCancelar(id)             -> ControlGestionarResultados::cancelarSolicitud()
 * verArchivo(id)                    -> ControlGestionarResultados::descargarArchivo()
 * @var array|null $paciente  (paso 1: @var string $criterio  @var array $pacientes)  (paso 2: @var array $examenes  @var bool $esPaciente)
 */
use App\Nucleo\Sesion;
?>
<h2>Resultados de laboratorio</h2>
<?php if (!$paciente): $rutaBusqueda = 'laboratorio'; require RAIZ . '/app/Interfaces/Pacientes/IUBuscarPaciente.php'; return; endif; ?>
<?php
$pid = (int) $paciente['persona_id'];
$puedeM = Sesion::puede('EXAMENES', 'M');
$boton = function (string $ruta, int $id, string $texto, string $confirmar, string $clase = 'btn-enlace') {
    return '<form method="post" action="?r=' . $ruta . '" data-confirmar="' . e($confirmar) . '">' . campoCsrf()
         . '<input type="hidden" name="id" value="' . $id . '"><button class="' . $clase . '">' . e($texto) . '</button></form>';
};
?>
<div class="encabezado">
  <h3><?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> · <?= e($paciente['nro_expediente']) ?></h3>
  <div class="botones">
    <?php if (Sesion::puede('PACIENTES', 'C')): ?><a class="btn secundario" href="?r=historial&amp;paciente_id=<?= $pid ?>">Historial</a><?php endif; ?>
    <?php if (Sesion::puede('EXAMENES', 'R') && $paciente['estado'] === 'ACTIVO'): ?>
      <a class="btn" href="?r=laboratorio/solicitar&amp;paciente_id=<?= $pid ?>">Solicitar examen</a>
    <?php endif; ?>
  </div>
</div>

<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Solicitado</th><th>Examen</th><th>Cita</th><th>Médico</th><th>Estado</th><th>Resultado</th><?php if (!$esPaciente): ?><th>Acciones</th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($examenes as $x): ?>
    <tr>
      <td class="sin-salto"><?= fechaHora($x['fecha_solicitud']) ?></td>
      <td><?= e($x['examen']) ?><br><span class="suave"><?= bs($x['costo']) ?></span></td>
      <td><?= e($x['cita_codigo']) ?></td><td><?= e($x['medico']) ?></td>
      <td><?= insignia($x['estado']) ?></td>
      <td>
        <?php if ($x['resultado_id']): ?>
          <span class="suave"><?= fechaHora($x['fecha_resultado']) ?></span><br>
          <?= $x['resultado'] ? nl2br(e($x['resultado'])) : '' ?>
          <?php if ($x['archivo_nombre']): ?><br><a href="?r=laboratorio/archivo&amp;id=<?= (int) $x['id'] ?>" target="_blank"><?= e($x['archivo_nombre']) ?> (<?= e($x['tipo_archivo']) ?>)</a><?php endif; ?>
        <?php else: ?>—<?php endif; ?>
      </td>
      <?php if (!$esPaciente): ?>
        <td class="acciones">
          <?php if ($puedeM && $x['estado'] === 'SOLICITADO'): ?><?= $boton('laboratorio/en-proceso', (int) $x['id'], 'En proceso', '¿Marcar el examen como en proceso?') ?><?php endif; ?>
          <?php if ($puedeM && in_array($x['estado'], ['SOLICITADO', 'EN_PROCESO'], true)): ?>
            <a href="?r=laboratorio/formulario&amp;id=<?= (int) $x['id'] ?>">Cargar resultado</a>
            <?= $boton('laboratorio/cancelar', (int) $x['id'], 'Cancelar', '¿Cancelar la solicitud de ' . $x['examen'] . '?', 'btn-enlace peligro') ?>
          <?php endif; ?>
          <?php if ($puedeM && $x['estado'] === 'RESULTADO_LISTO'): ?><?= $boton('laboratorio/entregar', (int) $x['id'], 'Entregado', '¿Marcar el resultado como entregado al paciente?') ?><?php endif; ?>
        </td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$examenes): ?><tr><td colspan="7" class="vacio">No hay exámenes de laboratorio.</td></tr><?php endif; ?>
  </tbody>
</table></div>
