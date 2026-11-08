-- =====================================================================================
--  Migración 10 - Permisos de los módulos del Ciclo 2
--
--  Por qué: en bases creadas con una versión anterior de 03_datos_base.sql solo
--  existen los módulos del Ciclo 1 (PACIENTES, CITAS, AGENDAS, SEGURIDAD,
--  ESPECIALIDADES_MEDICOS). Faltan PAGOS, EXAMENES, REPORTES y COMUNICACION, y sin
--  esas filas:
--    - ni el administrador puede abrir Pagos, Reportes o Notificaciones;
--    - el paciente no puede pagar la cita (CU14 redirige a CU19 y recibe 403).
--
--  Qué hace: crea los permisos que falten y los asigna a cada rol, igual que la
--  matriz RBAC de 4.1.1 del documento. Es aditivo e idempotente (se puede ejecutar
--  dos veces sin error).
--
--  Aplicar en el SQL Editor de Supabase, después de la 09.
-- =====================================================================================
BEGIN;

-- 1. Permisos de los módulos del Ciclo 2 (si ya existen, no se duplican)
INSERT INTO permiso (modulo, accion) VALUES
 ('EXAMENES','C'), ('EXAMENES','R'), ('EXAMENES','M'),
 ('PAGOS','C'), ('PAGOS','R'),
 ('REPORTES','C'),
 ('COMUNICACION','C'), ('COMUNICACION','M')
ON CONFLICT (modulo, accion) DO NOTHING;

-- 2. ADMINISTRADOR: todos los permisos
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM rol r CROSS JOIN permiso p
 WHERE r.nombre = 'ADMINISTRADOR'
ON CONFLICT DO NOTHING;

-- 3. RECEPCIONISTA
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (
      ('PACIENTES','C'), ('PACIENTES','R'), ('PACIENTES','M'),
      ('CITAS','C'), ('CITAS','R'), ('CITAS','M'),
      ('ESPECIALIDADES_MEDICOS','C'), ('AGENDAS','C'),
      ('PAGOS','C'), ('PAGOS','R'), ('EXAMENES','C'), ('REPORTES','C'),
      ('COMUNICACION','C'), ('COMUNICACION','M'))
 WHERE r.nombre = 'RECEPCIONISTA'
ON CONFLICT DO NOTHING;

-- 4. MEDICO
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (
      ('PACIENTES','C'),
      ('CITAS','C'), ('CITAS','R'), ('CITAS','M'),
      ('ESPECIALIDADES_MEDICOS','C'), ('AGENDAS','C'),
      ('EXAMENES','C'), ('EXAMENES','R'), ('EXAMENES','M'))
 WHERE r.nombre = 'MEDICO'
ON CONFLICT DO NOTHING;

-- 5. PACIENTE: agenda y paga sus citas (también las de su grupo familiar, CU22)
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (
      ('CITAS','C'), ('CITAS','R'),
      ('PAGOS','C'), ('PAGOS','R'),
      ('EXAMENES','C'),
      ('COMUNICACION','C'))
 WHERE r.nombre = 'PACIENTE'
ON CONFLICT DO NOTHING;

COMMIT;
