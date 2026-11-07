<?php
/**
 * «boundary» IUPreRegistrados — pacientes pre-registrados desde el portal (CU05).
 * @var array $pacientes
 */
?>
<div class="encabezado">
  <h2>Pacientes pre-registrados (portal)</h2>
  <a class="btn secundario" href="?r=pacientes">Volver a pacientes</a>
</div>
<p class="suave">Pacientes que se registraron solos desde el portal y aun no fueron validados por recepcion. Valide el carnet real y active el expediente.</p>

<table class="tabla">
  <thead><tr><th>Expediente</th><th>Paciente</th><th>CI</th><th>Telefono</th><th>Correo</th><th>Pre-registro</th><th>Activar</th></tr></thead>
  <tbody>
  <?php foreach ($pacientes as $p): ?>
    <tr>
      <td><?= e($p['nro_expediente']) ?></td>
      <td><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></td>
      <td><?= e($p['ci']) ?></td>
      <td><?= e($p['telefono']) ?></td>
      <td><?= e($p['correo'] ?? '') ?></td>
      <td><?= e($p['fecha_pre_registro'] ?? '') ?></td>
      <td>
        <form method="post" action="?r=pacientes/activar">
          <?= campoCsrf() ?>
          <input type="hidden" name="persona_id" value="<?= (int) $p['persona_id'] ?>">
          <label style="display:inline">Correo <input name="correo" value="<?= e($p['correo'] ?? '') ?>" placeholder="para crear la cuenta"></label>
          <button class="btn pequeno">Activar</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pacientes): ?>
    <tr><td colspan="7" class="vacio">No hay pacientes pre-registrados.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\app\Interfaces\Pacientes\IUPreRegistrados.php"