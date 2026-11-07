<?php
/**
 * «boundary» IUFormResultado — CU23 cargar el resultado de un examen solicitado.   Ciclo 2 · P8 · Responsable: Jackeline
 * ingresarResultado(resultado, archivo), presionarGuardar() -> ControlGestionarResultados::registrarResultado()
 * @var array $examen  @var array $resultado  @var array $errores
 */
?>
<h2>Cargar resultado · <?= e($examen['examen']) ?></h2>
<p>Paciente: <strong><?= e($examen['paciente']) ?></strong> (<?= e($examen['nro_expediente']) ?>) · cita <?= e($examen['cita_codigo']) ?>
   · solicitado el <?= fechaHora($examen['fecha_solicitud']) ?> · <?= insignia($examen['estado']) ?></p>
<form method="post" action="?r=laboratorio/guardar" class="formulario" enctype="multipart/form-data">
  <?= campoCsrf() ?>
  <input type="hidden" name="id" value="<?= (int) $examen['id'] ?>">
  <label>Resultado
    <textarea name="resultado" rows="6" maxlength="5000"><?= e($resultado['resultado'] ?? '') ?></textarea><?= errorCampo($errores, 'resultado') ?></label>
  <label>Archivo del laboratorio (PDF, JPG o PNG · máx. 2 MB)
    <input type="file" name="archivo" accept="application/pdf,image/jpeg,image/png"><?= errorCampo($errores, 'archivo') ?></label>
  <p class="suave">Al guardar, el examen pasa a «Resultado listo» y se avisa al paciente.</p>
  <div class="botones"><a class="btn secundario" href="?r=laboratorio&amp;paciente_id=<?= (int) $examen['paciente_id'] ?>">Cancelar</a><button class="btn">Guardar resultado</button></div>
</form>
