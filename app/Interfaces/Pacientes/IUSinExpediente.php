<?php
/**
 * «boundary» IUSinExpediente — Usuario con cuenta que todavía no es paciente (CU07, CU21, CU23).
 * El expediente se abre con su primera reserva «Para mí». Muestra los dependientes menores cuyo historial puede ver.
 * @var string $titulo  @var string $ruta  @var array $menores
 */
?>
<div class="encabezado"><h2><?= e($titulo) ?></h2></div>
<div class="tarjeta vacio-expediente">
  <p><strong>Aún no tiene expediente en la clínica.</strong></p>
  <p class="suave">Se crea con su primera cita. Mientras tanto, puede reservar una desde «Agendar cita».</p>
  <a class="btn" href="?r=citas/nueva">Agendar cita</a>
</div>
<?php if ($menores): ?>
  <h3>Menores a su cargo</h3>
  <div class="opciones-paciente">
    <?php foreach ($menores as $m): ?>
      <a class="tarjeta-paciente" href="?r=<?= e($ruta) ?>&amp;paciente_id=<?= (int) $m['familiar_id'] ?>">
        <strong><?= e($m['nombre']) ?></strong>
        <small class="suave"><?= e($m['nro_expediente']) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
