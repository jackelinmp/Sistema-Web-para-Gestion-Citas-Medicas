<?php use App\Nucleo\Sesion;
foreach (['ok' => 'exito', 'error' => 'error'] as $tipo => $clase):
  if ($m = Sesion::flash($tipo)): ?>
    <div class="alerta <?= $clase ?>" role="alert"><?= e($m) ?></div>
<?php endif; endforeach;
