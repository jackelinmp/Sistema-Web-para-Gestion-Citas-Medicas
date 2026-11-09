<?php
/**
 * «boundary» IUCrearCuenta — Crear cuenta (extensión de CU01, Ciclo 2).
 * Un adulto crea su usuario para reservar citas en línea, para sí mismo o para sus dependientes.
 * ingresarDatos(...), presionarCrearCuenta() -> ControlIniciarSesion::crearCuenta()
 * @var array $d  @var array $errores
 */
$v = fn(string $c) => e($d[$c] ?? '');
$err = fn(string $c) => errorCampo($errores, $c);
?>
<h2>Crear cuenta</h2>
<p class="suave">Para sacar fichas en línea, para usted o para otra persona (un hijo, un familiar).</p>
<form method="post" action="?r=sesion/registro/guardar" class="formulario">
  <?= campoCsrf() ?>
  <div class="fila">
    <label><span class="obligatorio">Nombres</span><input name="nombres" maxlength="60" value="<?= $v('nombres') ?>" required><?= $err('nombres') ?></label>
    <label><span class="obligatorio">Apellidos</span><input name="apellidos" maxlength="60" value="<?= $v('apellidos') ?>" required><?= $err('apellidos') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">CI</span><input name="ci" maxlength="15" value="<?= $v('ci') ?>" required><?= $err('ci') ?></label>
    <label><span class="obligatorio">Fecha de nacimiento</span><input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" value="<?= $v('fecha_nacimiento') ?>" required><?= $err('fecha_nacimiento') ?></label>
  </div>
  <div class="fila">
    <label><span class="obligatorio">Sexo</span>
      <select name="sexo" required><option value="">Seleccione</option>
        <?php foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $k => $t): ?><option value="<?= $k ?>" <?= ($d['sexo'] ?? '') === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
      </select><?= $err('sexo') ?></label>
    <label><span class="obligatorio">Celular</span><input name="telefono" maxlength="20" inputmode="tel" placeholder="71234567" value="<?= $v('telefono') ?>" required><?= $err('telefono') ?></label>
  </div>
  <label><span class="obligatorio">Correo electrónico</span><input type="email" name="correo" maxlength="120" value="<?= $v('correo') ?>" required autocomplete="username"><?= $err('correo') ?></label>
  <div class="fila">
    <label><span class="obligatorio">Contraseña</span><input type="password" name="contrasena" required minlength="8" autocomplete="new-password"><?= $err('contrasena') ?></label>
    <label><span class="obligatorio">Repita la contraseña</span><input type="password" name="confirmacion" required minlength="8" autocomplete="new-password"></label>
  </div>
  <small class="suave">Mínimo 8 caracteres, con letras y números. Debe ser mayor de 18 años.</small>
  <button class="btn grande">Crear cuenta</button>
  <p class="centrado"><a href="?r=sesion/formulario">Ya tengo una cuenta</a></p>
</form>
