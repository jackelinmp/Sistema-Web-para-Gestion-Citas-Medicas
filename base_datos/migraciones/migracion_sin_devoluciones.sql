-- Clínica Vida Salud — Solo si NO van a recargar la base desde cero (01 y 02).
-- Política sin devoluciones: al cancelar una cita, el pago ya cobrado se conserva (no pasa a Reembolsado).
-- Agrega además el parámetro del plazo para pagar. Se puede ejecutar más de una vez.

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
    -- Cancelada o inasistencia: el pago pendiente se anula; el pago ya cobrado se conserva
    -- (política de la clínica: no hay devoluciones de dinero).
    IF p_nuevo IN ('CANCELADA', 'INASISTENCIA') THEN
        UPDATE pago SET estado = 'ANULADO' WHERE cita_id = p_cita AND estado = 'PENDIENTE';
    END IF;
    PERFORM fn_registrar_bitacora(p_usuario, 'CITA_' || p_nuevo, 'Cita ' || p_cita || ' pasa de ' || v_actual || ' a ' || p_nuevo,
                                  NULL, 'cita', p_cita);
END;
$$;

INSERT INTO parametro_sistema (clave, valor, descripcion)
VALUES ('MINUTOS_PLAZO_PAGO', '30', 'Minutos que tiene el paciente para pagar una cita en línea antes de que se anule')
ON CONFLICT (clave) DO NOTHING;
