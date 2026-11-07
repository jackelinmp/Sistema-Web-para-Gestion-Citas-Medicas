-- =====================================================================================
--  Migración 08 — CU25/CU26: preguntas frecuentes iniciales del asistente virtual
--  La base se instala vacía; sin estas preguntas el asistente no puede responder nada.
--  Solo agrega las que no existen (se puede ejecutar dos veces). Luego se editan en CU26.
-- =====================================================================================
BEGIN;
INSERT INTO pregunta_frecuente (categoria, pregunta, respuesta, palabras_clave)
SELECT v.* FROM (VALUES
 ('GENERAL',  '¿Cuál es el horario de atención?', 'Lunes a viernes de 08:00 a 19:00 y sábados de 08:00 a 13:00.', 'horario atención abierto hora'),
 ('CITAS',    '¿Cómo agendo una cita médica?', 'Ingrese al sistema, elija «Agendar cita», seleccione especialidad, médico y un horario disponible.', 'agendar cita reservar turno sacar'),
 ('PAGOS',    '¿Qué métodos de pago aceptan?', 'Efectivo, tarjeta, QR, transferencia y pago en línea.', 'pago pagar tarjeta qr efectivo métodos'),
 ('CITAS',    '¿Puedo cancelar o reprogramar mi cita?', 'Sí, hasta 24 horas antes desde «Mis citas» o llamando a recepción.', 'cancelar reprogramar cambiar cita'),
 ('CITAS',    '¿Qué pasa si no puedo asistir?', 'Avise con anticipación. Las inasistencias repetidas pueden limitar nuevas reservas.', 'inasistencia faltar asistir'),
 ('PAGOS',    '¿Recibiré un comprobante?', 'Sí, al confirmar el pago se emite un comprobante con número correlativo.', 'comprobante factura recibo'),
 ('EXAMENES', '¿Cómo veo mis resultados de laboratorio?', 'Ingrese a «Resultados de laboratorio»; le avisaremos cuando estén listos.', 'resultados laboratorio examen'),
 ('CUENTA',   '¿Olvidé mi contraseña, qué hago?', 'En la pantalla de inicio pulse «¿Olvidó su contraseña?» y siga el enlace que llegará a su correo.', 'contraseña olvidé recuperar clave'),
 ('GENERAL',  '¿Dónde está la clínica?', 'Atendemos en La Paz, Bolivia. Consulte la dirección exacta en recepción.', 'dirección ubicación donde')
) AS v(categoria, pregunta, respuesta, palabras_clave)
WHERE NOT EXISTS (SELECT 1 FROM pregunta_frecuente f WHERE lower(f.pregunta) = lower(v.pregunta));
COMMIT;
