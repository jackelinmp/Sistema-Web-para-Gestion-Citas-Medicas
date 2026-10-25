-- =====================================================================================
--  05_supabase_seguridad.sql : SOLO para Supabase (en PostgreSQL local es opcional)
--  Supabase expone por defecto las tablas del esquema public mediante su API REST.
--  Activar RLS sin crear políticas las deja cerradas para esa API (anon/authenticated).
--  La aplicación PHP no se ve afectada: se conecta con el rol postgres, que ignora RLS.
-- =====================================================================================
DO $$
DECLARE t RECORD;
BEGIN
    FOR t IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', t.tablename);
    END LOOP;
END $$;
