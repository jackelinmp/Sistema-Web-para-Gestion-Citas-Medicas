-- Migración 07 — CU23: guarda el archivo del resultado de laboratorio en la base (antes estaba en app/schema.sql)
BEGIN;
ALTER TABLE resultado_laboratorio ADD COLUMN IF NOT EXISTS archivo BYTEA;

COMMENT ON COLUMN resultado_laboratorio.archivo IS 'CU23: contenido del archivo del resultado (PDF, JPG o PNG; máx. 2 MB lo controla la aplicación)';COMMIT;
