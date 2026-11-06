<?php
/**
 * «boundary» IUFichaClinica — CU21 Gestionar Ficha Clínica Básica.   Ciclo 2 · P8 · Responsable: Jackeline
 * solicitarFicha(paciente_id)                 -> ControlGestionarFichaClinica::mostrarFicha()
 * ingresarDatos(...), presionarGuardar()      -> ControlGestionarFichaClinica::guardarFicha()
 * Campos = columnas de ficha_clinica.
 * @var array $paciente  @var array|null $ficha  @var array $errores  @var bool $puedeModificar
 */
use App\Entidades\FichaClinica;

$v = fn(string $c) => e($ficha[$c] ?? 'No especificado');
$ro = $puedeModificar ? '' : 'readonly';
$areas = ['alergias' => 'Alergias', 'enfermedades_cronicas' => 'Enfermedades crónicas',
          'antecedentes_familiares' => 'Antecedentes familiares', 'antecedentes_quirurgicos' => 'Antecedentes quirúrgicos'];
?>
<div class="encabezado">
  <h2>Ficha clínica · <?= e($paciente['nombres'] . ' ' . $paciente['apellidos']) ?> (<?= e($paciente['nro_expediente']) ?>)</h2>
  <a class="btn secundario" href="?r=historial&paciente_id=<?= (int) $paciente['persona_id'] ?>">Volver al historial</a>
</div>
<?php if (!empty($ficha['fecha_actualizacion'])): ?>
  <p class="alerta info">Última actualización: <?= fechaHora($ficha['fecha_actualizacion']) ?></p>
<?php endif; ?>
<form method="post" action="?r=ficha/guardar" class="formulario ancho">
  <?= campoCsrf() ?>
  <input type="hidden" name="paciente_id" value="<?= (int) $paciente['persona_id'] ?>">
  <label>Tipo de sangre
    <select name="tipo_sangre" <?= $puedeModificar ? '' : 'disabled' ?>>
      <?php foreach (FichaClinica::TIPOS_SANGRE as $g): ?>
        <option <?= ($ficha['tipo_sangre'] ?? 'No especificado') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
      <?php endforeach; ?>
    </select><?= errorCampo($errores, 'tipo_sangre') ?>
  </label>
  <?php foreach ($areas as $campo => $texto): ?>
    <label><?= $texto ?>
      <textarea name="<?= $campo ?>" rows="2" maxlength="1000" <?= $ro ?>><?= $v($campo) ?></textarea><?= errorCampo($errores, $campo) ?>
    </label>
  <?php endforeach; ?>
  <?php if ($puedeModificar): ?>
    <div class="botones"><button class="btn">Guardar ficha</button></div>
  <?php endif; ?>
</form>
