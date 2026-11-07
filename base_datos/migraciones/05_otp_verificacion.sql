-- =====================================================================================
--  Migración 05 — Ciclo 2: OTP para pre-registro de pacientes (CU05)
--
--  Qué cambia (aditivo e idempotente):
--   1. Tabla otp_verificacion: código de 6 dígitos con expiración y contador de intentos.
--   2. Se usa para validar el teléfono del paciente antes de crear su cuenta.
--
--  Aplicar en el SQL Editor de Supabase.
-- =====================================================================================
BEGIN;

CREATE TABLE IF NOT EXISTS otp_verificacion (
    id              BIGSERIAL    PRIMARY KEY,
    paciente_id     BIGINT       NOT NULL REFERENCES paciente(persona_id) ON DELETE CASCADE,
    codigo          VARCHAR(6)   NOT NULL,
    telefono        VARCHAR(20)  NOT NULL,
    fecha_creacion  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    fecha_expiracion TIMESTAMPTZ NOT NULL,
    verificado      BOOLEAN      NOT NULL DEFAULT FALSE,
    intentos        SMALLINT     NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_otp_paciente ON otp_verificacion (paciente_id, fecha_creacion DESC);

COMMIT;
" path="C:\Users\prueb\Documents\Sistema-Web-para-Gestion-Citas-Medicas\base_datos\migraciones\05_otp_verificacion.sql"