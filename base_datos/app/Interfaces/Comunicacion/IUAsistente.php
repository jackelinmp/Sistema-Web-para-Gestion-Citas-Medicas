<?php
/**
 * «boundary» IUAsistente — CU25 Asistente virtual (chat con burbujas).
 * mostrarChat()          -> ControlAsistenteVirtual::mostrarChat()
 * enviarMensaje(texto)   -> ControlAsistenteVirtual::enviarMensaje()      (escribir, tocar una sugerencia o dictar 🎤)
 * cerrarConversacion()   -> ControlAsistenteVirtual::cerrarConversacion()  (botón «Nueva conversación»)
 * @var string $conversacionId  @var array $mensajes  @var array|null $usuario  @var string $textoInicial (comando de voz)
 * @var array $sugerencias  preguntas activas de la base de conocimiento + «Ver mis citas»
 */
?>
<div class="chat-caja">

  <div class="chat-cabecera">
    <span class="chat-avatar"><?= icono('chat', 20) ?></span>
    <div class="chat-titulo">
      <strong>Asistente Vida Salud</strong>
      <small><?= empty($usuario) ? 'Está escribiendo como visitante' : 'Hola, ' . e(strtok((string) $usuario['nombre'], ' ')) ?></small>
    </div>
    <?php if ($mensajes): ?>
      <form method="post" action="?r=asistente/cerrar">
        <?= campoCsrf() ?>
        <input type="hidden" name="conversacion_id" value="<?= e($conversacionId) ?>">
        <button class="btn-enlace">Nueva<span> conversación</span></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="chat" aria-live="polite">
    <div class="burbuja bot">¡Hola! Soy el asistente de la clínica. Pregúnteme por horarios, sus citas, pagos o cancelaciones.</div>
    <?php foreach ($mensajes as $m): ?>
      <div class="burbuja <?= $m['emisor'] === 'PACIENTE' ? 'yo' : 'bot' ?>">
        <?= nl2br(e($m['texto'])) ?>
        <time><?= e(substr((string) $m['fecha_hora'], 11, 5)) ?></time>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="chat-sugerencias">
    <?php foreach ($sugerencias as $s): ?>
      <form method="post" action="?r=asistente/enviar">
        <?= campoCsrf() ?>
        <input type="hidden" name="conversacion_id" value="<?= e($conversacionId) ?>">
        <button name="texto" value="<?= e($s) ?>"><?= e($s) ?></button>
      </form>
    <?php endforeach; ?>
  </div>

  <form class="chat-entrada" method="post" action="?r=asistente/enviar" <?= ($textoInicial ?? '') !== '' ? 'data-enviar-solo' : '' ?>>
    <?= campoCsrf() ?>
    <input type="hidden" name="conversacion_id" value="<?= e($conversacionId) ?>">
    <input type="text" name="texto" value="<?= e($textoInicial ?? '') ?>" maxlength="300" placeholder="Escriba su pregunta…"
           required autofocus autocomplete="off" aria-label="Mensaje">
    <button type="button" class="chat-boton secundario" data-dictado hidden title="Dictar por voz" aria-label="Dictar por voz"><?= icono('microfono', 18) ?></button>
    <button class="chat-boton" title="Enviar" aria-label="Enviar"><?= icono('enviar', 18) ?></button>
  </form>

  <?php if (empty($usuario)): ?>
    <p class="chat-pie"><a href="?r=sesion/formulario">Iniciar sesión</a> para ver sus citas · <a href="?r=sesion/registro">Soy paciente nuevo</a></p>
  <?php endif; ?>
</div>
