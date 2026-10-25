<?php
/**
 * «boundary» IUPanel — pantalla de inicio según el rol (CU01, paso 5). Ciclo 1 · P1
 * Muestra los módulos por CICLO y paquete. Lo que el rol no puede usar, o que es del Ciclo 2,
 * aparece bloqueado con su motivo (no se oculta).
 * seleccionarCiclo(n) -> ControlIniciarSesion::mostrarPanel()
 * @var array  $ciclos       salida de Ciclos::evaluar()
 * @var string $seleccion    '1', '2' o 'todos'
 * @var array  $indicadores  [rotulo => valor] solo de lo que el rol puede consultar
 */
use App\Nucleo\Ciclos;
use App\Nucleo\Sesion;

$usuario = Sesion::usuario();
$mostrar = $seleccion === 'todos' ? array_keys($ciclos) : [(int) $seleccion];
?>
<section class="bienvenida">
  <div>
    <span class="etiqueta-cu">CU01</span>
    <h2>Hola, <?= e($usuario['nombre']) ?></h2>
    <p class="suave">Bienvenido(a) a Clínica Vida Salud. Estos son los casos de uso disponibles para su rol.</p>
  </div>
  <span class="rol-chip grande"><?= e($usuario['rol']) ?></span>
</section>

<?php if ($indicadores): ?>
<div class="indicadores">
  <?php foreach ($indicadores as $rotulo => $valor): ?>
    <div class="indicador"><div class="valor"><?= (int) $valor ?></div><div class="rotulo"><?= e($rotulo) ?></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<nav class="pestanas-ciclo" aria-label="Ciclos del proyecto">
  <?php foreach ($ciclos as $n => $ciclo): ?>
    <a href="?r=panel&amp;ciclo=<?= $n ?>" class="<?= $seleccion === (string) $n ? 'activa' : '' ?> <?= $ciclo['abierto'] ? '' : 'cerrado' ?>">
      <?= e($ciclo['nombre']) ?>
      <?php if ($ciclo['abierto']): ?><small><?= (int) $ciclo['disponibles'] ?>/<?= (int) $ciclo["total"] ?> casos de uso</small>
      <?php else: ?><?= icono('candado', 14) ?><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <a href="?r=panel&amp;ciclo=todos" class="<?= $seleccion === 'todos' ? 'activa' : '' ?>">Todos</a>
</nav>

<?php foreach ($mostrar as $n): $ciclo = $ciclos[$n]; ?>
  <section class="ciclo-bloque">
    <header class="ciclo-cabecera">
      <h3><?= e($ciclo['nombre']) ?> <span class="suave">· <?= e($ciclo['titulo']) ?></span></h3>
      <?php if (!$ciclo['abierto']): ?>
        <span class="chip-bloqueo"><?= icono('candado', 14) ?> Bloqueado hasta iniciar el Ciclo 2</span>
      <?php endif; ?>
    </header>

    <?php foreach ($ciclo['paquetes'] as $paquete): ?>
      <h4 class="paquete-titulo"><?= e($paquete['id']) ?> · <?= e($paquete['titulo']) ?></h4>
      <div class="modulos">
        <?php foreach ($paquete['items'] as $it): $ok = $it['estado'] === 'ok'; ?>
          <article class="modulo <?= $ok ? '' : 'bloqueado' ?>">
            <div class="modulo-cabecera">
              <span class="modulo-icono"><?= icono($it['icono'], 22) ?></span>
              <?= etiquetaCu($it) ?>
            </div>
            <h5><?= e($it['texto']) ?></h5>
            <p><?= e($it['desc']) ?></p>
            <?php if ($ok): ?>
              <a class="btn" href="?r=<?= e($it['ruta']) ?>">Ingresar <?= icono('flecha', 16) ?></a>
            <?php else: ?>
              <span class="btn-bloqueado" role="note"><?= icono('candado', 15) ?> <?= e(Ciclos::motivo($it['estado'])) ?></span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
