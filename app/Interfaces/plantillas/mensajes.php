<?php use App\Nucleo\Sesion;
foreach (['ok' => 'exito', 'error' => 'error', 'aviso' => 'aviso', 'info' => 'info'] as $tipo => $clase):
  if ($m = Sesion::flash($tipo)): ?>
    <div class="alerta <?= $clase ?>" role="alert"><?= e($m) ?></div>
<?php endif; endforeach;
