-- =====================================================================================
--  Migración 11 - Avisos por WhatsApp (CU24) a elección del paciente
--
--  Por qué: los avisos de cita llegan en pantalla y por correo. El paciente puede
--  pedir además recibirlos por WhatsApp desde «Mi perfil». Se guarda esa elección
--  en la cuenta (usuario), porque el aviso va a quien maneja las citas: el propio
--  paciente o el padre/tutor que las sacó.
--
--  Qué hace: agrega una sola columna, con valor FALSE por defecto (nadie recibe
--  WhatsApp sin activarlo). Aditiva e idempotente.
--
--  Aplicar en el SQL Editor de Supabase, después de la 10.
-- =====================================================================================
ALTER TABLE usuario ADD COLUMN IF NOT EXISTS avisos_whatsapp BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN usuario.avisos_whatsapp IS
  'CU24: el titular de la cuenta acepta recibir los avisos de sus citas por WhatsApp (al celular de su persona).';
