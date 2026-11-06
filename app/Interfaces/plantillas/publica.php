<?php /** Plantilla de páginas sin sesión (iniciar sesión, autorregistro). */ ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clínica Vida Salud</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body class="publica">
  <main class="tarjeta">
    <h1 class="marca">Clínica Vida Salud</h1>
    <?php require __DIR__ . '/mensajes.php'; ?>
    <?= $contenido ?>
  </main>
</body>
</html>
