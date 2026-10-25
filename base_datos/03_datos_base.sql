-- =====================================================================================
--  03_datos_base.sql : datos mínimos para que el sistema funcione (roles, permisos,
--  parámetros y el administrador inicial. Todo lo demás (especialidades, médicos, pacientes, citas...) se crea desde el sistema.)
--  Administrador inicial:  admin@clinicavidasalud.com  /  Admin123*   (pide cambiar la clave al entrar)
-- =====================================================================================
INSERT INTO rol (nombre, descripcion) VALUES
 ('ADMINISTRADOR', 'Control total del sistema'),
 ('RECEPCIONISTA', 'Registra pacientes y agenda citas'),
 ('MEDICO',        'Consulta su agenda y sus pacientes'),
 ('PACIENTE',      'Agenda sus propias citas desde el portal');

-- Acciones: C = consultar, R = registrar, M = modificar, A = activar/desactivar
INSERT INTO permiso (modulo, accion) VALUES
 ('PACIENTES','C'),('PACIENTES','R'),('PACIENTES','M'),
 ('ESPECIALIDADES_MEDICOS','C'),('ESPECIALIDADES_MEDICOS','R'),('ESPECIALIDADES_MEDICOS','M'),('ESPECIALIDADES_MEDICOS','A'),
 ('AGENDAS','C'),('AGENDAS','M'),
 ('CITAS','C'),('CITAS','R'),
 ('SEGURIDAD','C'),('SEGURIDAD','R'),('SEGURIDAD','M'),('SEGURIDAD','A'),
 -- módulos del Ciclo 2 (el código del Ciclo 1 aún no los usa)
 ('EXAMENES','C'),('EXAMENES','R'),('EXAMENES','M'),('PAGOS','C'),('PAGOS','R'),('REPORTES','C'),('COMUNICACION','C'),('COMUNICACION','M');

-- ADMINISTRADOR: todos los permisos
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r CROSS JOIN permiso p WHERE r.nombre = 'ADMINISTRADOR';
-- RECEPCIONISTA
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('PACIENTES','C'),('PACIENTES','R'),('PACIENTES','M'),('CITAS','C'),('CITAS','R'),
                              ('ESPECIALIDADES_MEDICOS','C'),('AGENDAS','C'),('PAGOS','C'),('PAGOS','R'),('EXAMENES','C'),('REPORTES','C'))
 WHERE r.nombre = 'RECEPCIONISTA';
-- MEDICO
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('PACIENTES','C'),('CITAS','C'),('CITAS','R'),('ESPECIALIDADES_MEDICOS','C'),('AGENDAS','C'),('EXAMENES','C'),('EXAMENES','R'),('EXAMENES','M'))
 WHERE r.nombre = 'MEDICO';
-- PACIENTE
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('CITAS','C'),('CITAS','R'),('PAGOS','C'),('PAGOS','R'),('EXAMENES','C'),('COMUNICACION','C'))
 WHERE r.nombre = 'PACIENTE';

INSERT INTO parametro_sistema (clave, valor, descripcion) VALUES
 ('INTENTOS_MAX_LOGIN',   '3',  'Intentos fallidos antes de bloquear la cuenta'),
 ('MINUTOS_BLOQUEO',      '15', 'Minutos que dura el bloqueo temporal de la cuenta'),
 ('MINUTOS_ENLACE_RECUP', '30', 'Minutos de validez del enlace para recuperar contraseña');

-- Administrador inicial
WITH p AS (
  INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion)
  VALUES ('1000001', 'Administrador', 'del Sistema', DATE '1985-03-15', 'M', '70000001', 'Clínica Vida Salud')
  RETURNING id)
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, debe_cambiar_contrasena)
SELECT p.id, (SELECT id FROM rol WHERE nombre = 'ADMINISTRADOR'), 'admin@clinicavidasalud.com',
       '$2y$10$EZzm0CFl83q5MiQmI6S/D.sROJR0GzMQUvrGGblizusuFnGgQ31Ui', TRUE
  FROM p;
