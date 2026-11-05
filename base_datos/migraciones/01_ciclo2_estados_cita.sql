-- =====================================================================================
--  Migración 01 — Ciclo 2
--  CU15 Gestionar Estados de Cita / CU17 Registrar Inasistencia
--
--  Qué cambia:
--   1. sp_cambiar_estado_cita permite PROGRAMADA -> INASISTENCIA
--      (antes solo CONFIRMADA -> INASISTENCIA, y CU17 exige ambos casos).
--   2. Agrega el permiso CITAS/M (modificar) y lo asigna a Administrador,
--      Recepcionista y Médico. Sin él, el menú de CU15 aparece bloqueado.
--
--  Es aditiva e idempotente: se puede ejecutar varias veces sin error.
--  Aplicar en el SQL Editor de Supabase ANTES de probar el código de CU15.
-- =====================================================================================
BEGIN;

CREATE OR REPLACE FUNCTION sp_cambiar_estado_cita(p_cita BIGINT, p_nuevo VARCHAR, p_usuario BIGINT)
RETURNS VOID
LANGUAGE plpgsql AS $$
DECLARE
    v_actual VARCHAR;
BEGIN
    SELECT estado INTO v_actual FROM cita WHERE id = p_cita FOR UPDATE;
    IF v_actual IS NULL THEN
        RAISE EXCEPTION 'La cita % no existe', p_cita;
    END IF;
    IF NOT (   (v_actual = 'PROGRAMADA' AND p_nuevo IN ('CONFIRMADA', 'CANCELADA', 'INASISTENCIA'))
            OR (v_actual = 'CONFIRMADA' AND p_nuevo IN ('ATENDIDA', 'CANCELADA', 'INASISTENCIA'))) THEN
        RAISE EXCEPTION 'No se puede pasar una cita de % a %', v_actual, p_nuevo;
    END IF;
    PERFORM set_config('app.usuario_id', p_usuario::text, TRUE);
    UPDATE cita SET estado = p_nuevo WHERE id = p_cita;
    IF p_nuevo = 'CANCELADA' THEN
        UPDATE pago SET estado = 'ANULADO' WHERE cita_id = p_cita AND estado = 'PENDIENTE';
    END IF;
    PERFORM fn_registrar_bitacora(p_usuario, 'CITA_' || p_nuevo, 'Cita ' || p_cita || ' pasa de ' || v_actual || ' a ' || p_nuevo,
                                  NULL, 'cita', p_cita);
END;
$$;

INSERT INTO permiso (modulo, accion) VALUES ('CITAS', 'M')
ON CONFLICT (modulo, accion) DO NOTHING;

INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id
  FROM rol r
  JOIN permiso p ON p.modulo = 'CITAS' AND p.accion = 'M'
 WHERE r.nombre IN ('ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO')
ON CONFLICT DO NOTHING;

COMMIT;
