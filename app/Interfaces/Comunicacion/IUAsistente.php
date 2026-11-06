<?php
/**
 * «boundary» IUAsistente — CU25 Asistente virtual.
 * mostrarChat()     -> ControlAsistenteVirtual::mostrarChat()
 * enviarMensaje()   -> ControlAsistenteVirtual::enviarMensaje()
 * cerrarConversacion() -> ControlAsistenteVirtual::cerrarConversacion()
 * @var string $conversacionId  @var array $mensajes  @var array|null $usuario
 */
?>
<div class="encabezado">
  <h2>Asistente virtual</h2>
  <?php if (empty($usuario)): ?>
    <p class="suave">Está conversando como visitante. Inicie sesión para ver sus citas.</p>
  <?php endif; ?>
</div>

<div class="chat">
  <?php foreach ($mensajes as $m): ?>
    <div class="mensaje <?= $m['emisor'] === 'PACIENTE' ? 'propio' : 'ajeno' ?>">
      <strong><?= $m['emisor'] === 'PACIENTE' ? 'Usted' : 'Asistente' ?>:</strong>
      <div><?= nl2br(e($m['texto'])) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$mensajes): ?>
    <p class="suave">Hola, ¿en qué puedo ayudarle? Pregunte por horarios, sus citas, cancelaciones o dudas generales.</p>
  <?php endif; ?>
</div>

<form class="chat-form" method="post" action="?r=asistente/enviar">
  <?= campoCsrf() ?>
  <input type="hidden" name="conversacion_id" value="<?= e($conversacionId) ?>">
  <input type="text" name="texto" placeholder="Escriba su consulta..." required autofocus style="width:70%">
  <button class="btn">Enviar</button>
</form>

<form method="post" action="?r=asistente/cerrar" style="margin-top:8px">
  <?= campoCsrf() ?>
  <input type="hidden" name="conversacion_id" value="<?= e($conversacionId) ?>">
  <button class="btn secundario">Cerrar conversación</button>
</form>
