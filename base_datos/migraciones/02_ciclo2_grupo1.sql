-- =====================================================================================
--  Migración 02 — Ciclo 2, Grupo 1
--  CU16 Reprogramar y cancelar cita · CU17 (corrección) · CU18 Reportes
--  CU24 Notificaciones · CU25 Asistente virtual · CU26 Base de conocimiento
--
--  Qué cambia (todo aditivo e idempotente: se puede ejecutar dos veces sin error):
--   1. cita.motivo_cancelacion  -> CU16 guarda por qué se canceló (se muestra al paciente y en reportes).
--   2. cita.veces_reprogramada  -> CU16 limita cuántas veces se mueve una misma cita.
--   3. cita.origen acepta 'CHATBOT' -> CU25 deja constancia de las citas reservadas desde el asistente.
--   4. Parámetros nuevos: HORAS_MINIMAS_CAMBIO (24) y MAX_REPROGRAMACIONES (2).
--   5. sp_cambiar_estado_cita (corrección de lógica de negocio):
--        CANCELADA    -> pago PENDIENTE pasa a ANULADO (igual que antes)
--                        pago PAGADO    pasa a REEMBOLSADO (antes quedaba PAGADO en una cita cancelada)
--        INASISTENCIA -> pago PENDIENTE pasa a ANULADO (antes quedaba pendiente para siempre)
--   6. sp_reprogramar_cita (nuevo): mueve la MISMA cita a otro horario (conserva código y pago),
--      suma veces_reprogramada, crea el evento CITA_REPROGRAMADA y deja la bitácora.
--   7. Permisos: el Recepcionista recibe COMUNICACION/C y COMUNICACION/M
--      (enviar recordatorios CU24 y mantener la base de conocimiento CU26).
--
--  Aplicar en el SQL Editor de Supabase ANTES de subir el código del Grupo 1.
-- =====================================================================================
BEGIN;

-- 1 y 2. Columnas nuevas de cita (CU16)
ALTER TABLE cita ADD COLUMN IF NOT EXISTS motivo_cancelacion VARCHAR(200);
ALTER TABLE cita ADD COLUMN IF NOT EXISTS veces_reprogramada SMALLINT NOT NULL DEFAULT 0;

-- 3. Origen CHATBOT (CU25). Se reemplaza solo la regla CHECK; los datos no cambian.
ALTER TABLE cita DROP CONSTRAINT IF EXISTS cita_origen_check;
ALTER TABLE cita ADD CONSTRAINT cita_origen_check
    CHECK (origen IN ('RECEPCION', 'MEDICO', 'PORTAL', 'CHATBOT'));

-- 4. Parámetros de negocio (editables sin tocar el código)
INSERT INTO parametro_sistema (clave, valor, descripcion) VALUES
 ('HORAS_MINIMAS_CAMBIO', '24', 'Horas de anticipación con que el paciente puede cancelar o reprogramar su cita'),
 ('MAX_REPROGRAMACIONES', '2',  'Veces que se puede reprogramar una misma cita')
ON CONFLICT (clave) DO NOTHING;

-- 5. Cambio de estado con el efecto correcto sobre el pago
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
        UPDATE pago SET estado = CASE estado WHEN 'PENDIENTE' THEN 'ANULADO' ELSE 'REEMBOLSADO' END
         WHERE cita_id = p_cita AND estado IN ('PENDIENTE', 'PAGADO');
    ELSIF p_nuevo = 'INASISTENCIA' THEN
        UPDATE pago SET estado = 'ANULADO' WHERE cita_id = p_cita AND estado = 'PENDIENTE';
    END IF;
    PERFORM fn_registrar_bitacora(p_usuario, 'CITA_' || p_nuevo, 'Cita ' || p_cita || ' pasa de ' || v_actual || ' a ' || p_nuevo,
                                  NULL, 'cita', p_cita);
END;
$$;

-- 6. Reprogramar: la misma cita cambia de médico (de la misma especialidad), fecha y hora
CREATE OR REPLACE FUNCTION sp_reprogramar_cita(p_cita BIGINT, p_medico BIGINT, p_fecha DATE,
                                               p_hora_inicio TIME, p_hora_fin TIME, p_usuario BIGINT)
RETURNS VOID
LANGUAGE plpgsql AS $$
DECLARE
    v cita%ROWTYPE;
BEGIN
    SELECT * INTO v FROM cita WHERE id = p_cita FOR UPDATE;
    IF v.id IS NULL THEN
        RAISE EXCEPTION 'La cita % no existe', p_cita;
    END IF;
    IF v.estado NOT IN ('PROGRAMADA', 'CONFIRMADA') THEN
        RAISE EXCEPTION 'Solo se puede reprogramar una cita programada o confirmada';
    END IF;
    IF p_fecha + p_hora_inicio < localtimestamp THEN
        RAISE EXCEPTION 'No se puede reprogramar a una fecha u hora pasada';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM medico WHERE persona_id = p_medico AND estado = 'ACTIVO') THEN
        RAISE EXCEPTION 'El médico seleccionado no está activo';
    END IF;
    PERFORM set_config('app.usuario_id', p_usuario::text, TRUE);
    -- ex_cita_medico y ex_cita_paciente siguen impidiendo horarios superpuestos;
    -- fk_cita_medico_especialidad exige que el nuevo médico ejerza la misma especialidad.
    UPDATE cita
       SET medico_id = p_medico, fecha = p_fecha, hora_inicio = p_hora_inicio, hora_fin = p_hora_fin,
           veces_reprogramada = veces_reprogramada + 1
     WHERE id = p_cita;
    INSERT INTO evento_paciente (paciente_id, tipo_evento, payload)
    VALUES (v.paciente_id, 'CITA_REPROGRAMADA',
            jsonb_build_object('cita_id', v.id, 'codigo', v.codigo, 'estado', v.estado,
                               'fecha', p_fecha, 'hora_inicio', p_hora_inicio, 'medico_id', p_medico,
                               'fecha_anterior', v.fecha, 'hora_anterior', v.hora_inicio));
    PERFORM fn_registrar_bitacora(p_usuario, 'CITA_REPROGRAMADA',
        'Cita ' || v.codigo || ' pasa del ' || v.fecha || ' ' || to_char(v.hora_inicio, 'HH24:MI')
        || ' al ' || p_fecha || ' ' || to_char(p_hora_inicio, 'HH24:MI'), NULL, 'cita', p_cita);
END;
$$;

-- 7. Permisos del Recepcionista para CU24 y CU26
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id
  FROM rol r
  JOIN permiso p ON p.modulo = 'COMUNICACION' AND p.accion IN ('C', 'M')
 WHERE r.nombre = 'RECEPCIONISTA'
ON CONFLICT DO NOTHING;

-- Vista de detalle con las columnas nuevas (se agregan al final: CREATE OR REPLACE lo permite)
CREATE OR REPLACE VIEW vw_citas_detalle AS
SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.origen, c.motivo_consulta,
       c.paciente_id, pp.ci AS paciente_ci, pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente,
       c.medico_id, pm.nombres || ' ' || pm.apellidos AS medico, m.matricula,
       c.especialidad_id, e.nombre AS especialidad, e.costo_consulta,
       pg.estado AS pago_estado, pg.monto, pg.metodo,
       c.motivo_cancelacion, c.veces_reprogramada
  FROM cita c
  JOIN paciente pa     ON pa.persona_id = c.paciente_id
  JOIN persona pp      ON pp.id = c.paciente_id
  JOIN medico m        ON m.persona_id = c.medico_id
  JOIN persona pm      ON pm.id = c.medico_id
  JOIN especialidad e  ON e.id = c.especialidad_id
  LEFT JOIN pago pg    ON pg.cita_id = c.id;

COMMIT;
