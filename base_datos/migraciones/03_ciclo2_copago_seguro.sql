-- =====================================================================================
--  Migración 03 — Ciclo 2: copago en seguros médicos (CU19)
--
--  Qué cambia (aditivo e idempotente):
--   1. pago.copago -> monto que le corresponde pagar al paciente de su bolsillo
--      cuando la cita se confirma por seguro (el resto lo cubre la aseguradora).
--   2. pago.copago_metodo / copago_referencia -> cómo se cobró ese copago en el mostrador.
--   3. pago.copago_fecha -> cuándo se cobró el copago.
--
--  Aplicar en el SQL Editor de Supabase ANTES de probar la confirmación por seguro con copago.
-- =====================================================================================
BEGIN;

ALTER TABLE pago ADD COLUMN IF NOT EXISTS copago NUMERIC(10,2) NOT NULL DEFAULT 0;
ALTER TABLE pago ADD COLUMN IF NOT EXISTS copago_metodo VARCHAR(15)
    CHECK (copago_metodo IS NULL OR copago_metodo IN ('EFECTIVO','QR','TRANSFERENCIA','TARJETA'));
ALTER TABLE pago ADD COLUMN IF NOT EXISTS copago_referencia VARCHAR(100);
ALTER TABLE pago ADD COLUMN IF NOT EXISTS copago_fecha TIMESTAMPTZ;

COMMIT;
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\base_datos\migraciones\03_ciclo2_copago_seguro.sql"