-- =====================================================================================
--  Migración 09 — Catálogos que el sistema necesita y que no tienen pantalla para crearlos
--   - aseguradora  (CU05 póliza del paciente, CU19 pago por seguro / copago)
--   - tipo_examen  (CU23 solicitud y resultados de laboratorio)
--  La base se instala vacía. Solo agrega lo que no existe (se puede ejecutar dos veces).
-- =====================================================================================
BEGIN;
INSERT INTO aseguradora (nombre) VALUES
 ('Caja Nacional de Salud'), ('Seguro Universitario (SSU)'), ('Nacional Seguros'),
 ('Alianza Seguros'), ('Seguros Illimani')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO tipo_examen (nombre, descripcion, costo)
SELECT v.* FROM (VALUES
 ('Hemograma completo',      'Recuento de células de la sangre',        45.00),
 ('Glucosa en ayunas',       'Control de azúcar en sangre',             25.00),
 ('Perfil lipídico',         'Colesterol y triglicéridos',              80.00),
 ('Examen general de orina', 'Análisis físico, químico y microscópico', 30.00),
 ('Radiografía de tórax',    'Imagen del tórax',                       120.00),
 ('Electrocardiograma',      'Actividad eléctrica del corazón',         90.00),
 ('Ecografía abdominal',     'Imagen por ultrasonido del abdomen',     150.00),
 ('Prueba de embarazo',      'Detección de hCG en sangre',              35.00)
) AS v(nombre, descripcion, costo)
WHERE NOT EXISTS (SELECT 1 FROM tipo_examen t WHERE lower(t.nombre) = lower(v.nombre));
COMMIT;
