-- =====================================================================================
--  Migración 12 - El responsable de un dependiente no necesita ser paciente
--
--  Por qué: se separa la cuenta web (usuario) del expediente clínico (paciente).
--  Un padre o tutor puede tener cuenta solo para manejar las citas de su hijo sin
--  atenderse en la clínica. Hasta ahora vinculo_familiar exigía que el responsable
--  (columna paciente_id) también tuviera expediente.
--
--  Qué hace: la columna vinculo_familiar.paciente_id pasa a referenciar persona(id)
--  en lugar de paciente(persona_id). Los datos no cambian (todo paciente es persona).
--  El dependiente (familiar_id) sigue siendo paciente. Idempotente.
--
--  Aplicar en el SQL Editor de Supabase, después de la 11.
-- =====================================================================================
BEGIN;
ALTER TABLE vinculo_familiar DROP CONSTRAINT IF EXISTS vinculo_familiar_paciente_id_fkey;
ALTER TABLE vinculo_familiar ADD CONSTRAINT vinculo_familiar_paciente_id_fkey
      FOREIGN KEY (paciente_id) REFERENCES persona(id) ON DELETE CASCADE;
COMMENT ON COLUMN vinculo_familiar.paciente_id IS
  'Responsable (persona con cuenta). No necesita expediente propio: puede ser un padre o tutor que no se atiende.';
COMMIT;
