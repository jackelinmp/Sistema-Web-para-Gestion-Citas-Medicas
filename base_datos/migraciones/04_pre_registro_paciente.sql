-- =====================================================================================
--  Migración 04 — Ciclo 2: pre-registro de pacientes externos (CU05/CU14)
--
--  Qué cambia (aditivo e idempotente):
--   1. paciente.estado acepta 'PRE_REGISTRADO' (paciente que se registró solo desde
--      el portal y aún no fue validado por recepción).
--   2. paciente.telefono_verificado: si el número de celular fue validado (OTP).
--   3. paciente.fecha_pre_registro: cuándo se pre-registró.
--   4. paciente.validado_por: recepcionista que activó el expediente.
--
--  Aplicar en el SQL Editor de Supabase.
-- =====================================================================================
BEGIN;

-- 1. Ampliar el CHECK de estado (drop + recreate, aditivo).
ALTER TABLE paciente DROP CONSTRAINT IF EXISTS paciente_estado_check;
ALTER TABLE paciente ADD CONSTRAINT paciente_estado_check
    CHECK (estado IN ('ACTIVO', 'INACTIVO', 'PRE_REGISTRADO'));

-- 2. Columnas nuevas.
ALTER TABLE paciente ADD COLUMN IF NOT EXISTS telefono_verificado BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE paciente ADD COLUMN IF NOT EXISTS fecha_pre_registro TIMESTAMPTZ;
ALTER TABLE paciente ADD COLUMN IF NOT EXISTS validado_por BIGINT REFERENCES usuario(id);

COMMIT;
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\base_datos\migraciones\04_pre_registro_paciente.sql"