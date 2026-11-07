-- =====================================================================================
--  Migración 06 — Ciclo 2: tutor_id para dependientes (CU22) + limpieza pre-registro
--
--  Qué cambia (aditivo e idempotente):
--   1. persona.tutor_id -> si apunta a otra persona, es un menor/dependiente.
--   2. paciente.estado vuelve a solo ACTIVO/INACTIVO (se elimina PRE_REGISTRADO).
--      Los pacientes pre-registrados pasan a ACTIVO.
--
--  Aplicar en el SQL Editor de Supabase.
-- =====================================================================================
BEGIN;

-- 1. tutor_id en persona
ALTER TABLE persona ADD COLUMN IF NOT EXISTS tutor_id BIGINT REFERENCES persona(id);

-- 2. Limpiar pre-registrados: pasarlos a ACTIVO
UPDATE paciente SET estado = 'ACTIVO' WHERE estado = 'PRE_REGISTRADO';

-- 3. Restaurar CHECK de estado (sin PRE_REGISTRADO)
ALTER TABLE paciente DROP CONSTRAINT IF EXISTS paciente_estado_check;
ALTER TABLE paciente ADD CONSTRAINT paciente_estado_check
    CHECK (estado IN ('ACTIVO', 'INACTIVO'));

COMMIT;
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\base_datos\migraciones\06_tutor_dependientes.sql"