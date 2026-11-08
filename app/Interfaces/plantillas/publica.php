<?php /** Plantilla de páginas sin sesión (iniciar sesión, recuperar y restablecer contraseña). */
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clínica Vida Salud</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="publica">
  <div class="acceso">
    <aside class="acceso-marca">
      <span class="logo grande"><?= icono('corazon', 30) ?></span>
      <h1>Clínica Vida Salud</h1>
      <p>Portal de citas médicas</p>
      <ul>
        <li><?= icono('calendario-ok', 18) ?> Reserve y pague sus consultas en línea, por especialidad y médico</li>
        <li><?= icono('paciente', 18) ?> Saque fichas para usted, sus hijos o un familiar desde una sola cuenta</li>
        <li><?= icono('escudo', 18) ?> Reciba confirmaciones y recordatorios por correo o WhatsApp</li>
      </ul>
      <small>Sus datos clínicos están protegidos: solo los ven usted y el personal autorizado.</small>
    </aside>
    <main class="tarjeta acceso-form">
      <?php require __DIR__ . '/mensajes.php'; ?>
      <?= $contenido ?>
    </main>
  </div>
  <script src="js/app.js"></script>
</body>
</html>
