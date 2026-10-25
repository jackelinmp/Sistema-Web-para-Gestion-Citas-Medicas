-- =====================================================================================
--  02_funciones_triggers.sql : funciones, procedimientos almacenados y triggers
--  Lo que el código PHP llama directamente: fn_registrar_bitacora, fn_cupos_disponibles,
--  fn_especialidades_permitidas. El resto son reglas que la base hace cumplir sola.
-- =====================================================================================

-- ---------- Bitácora (RN14): registrar y proteger -------------------------------------
CREATE OR REPLACE FUNCTION fn_registrar_bitacora(
    p_usuario     BIGINT,
    p_accion      VARCHAR,
    p_descripcion TEXT,
    p_ip          INET,
    p_tabla       VARCHAR,
    p_registro    BIGINT
) RETURNS VOID
LANGUAGE sql AS $$
    INSERT INTO bitacora (usuario_id, accion, descripcion, ip, tabla, registro_id)
    VALUES (p_usuario, p_accion, p_descripcion, p_ip, p_tabla, p_registro);
$$;

CREATE OR REPLACE FUNCTION tg_bitacora_solo_insercion() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'La bitácora es de solo inserción: no se puede modificar ni eliminar (RN14)';
END;
$$;
CREATE TRIGGER tg_bitacora_solo_insercion
    BEFORE UPDATE OR DELETE ON bitacora
    FOR EACH ROW EXECUTE FUNCTION tg_bitacora_solo_insercion();

-- ---------- CU06: especialidades permitidas por edad y sexo del paciente --------------
-- Edad calculada a la fecha de la cita. edad_maxima NULL = sin límite superior.
CREATE OR REPLACE FUNCTION fn_especialidades_permitidas(p_paciente BIGINT, p_fecha DATE)
RETURNS TABLE (id SMALLINT, nombre VARCHAR, costo_consulta NUMERIC)
LANGUAGE sql STABLE AS $$
    SELECT e.id, e.nombre, e.costo_consulta
      FROM especialidad e
      JOIN persona p ON p.id = p_paciente
     WHERE e.estado
       AND date_part('year', age(p_fecha, p.fecha_nacimiento)) >= e.edad_minima
       AND (e.edad_maxima IS NULL OR date_part('year', age(p_fecha, p.fecha_nacimiento)) <= e.edad_maxima)
       AND e.sexo_permitido IN ('A', p.sexo)
     ORDER BY e.nombre;
$$;

-- ---------- CU13: cupos libres de un médico en una fecha ------------------------------
-- jornada vigente del día - citas activas - bloqueos de agenda - horas que ya pasaron
CREATE OR REPLACE FUNCTION fn_cupos_disponibles(p_medico BIGINT, p_fecha DATE)
RETURNS TABLE (hora_inicio TIME, hora_fin TIME)
LANGUAGE sql STABLE AS $$
    SELECT s.ini::time, (s.ini + make_interval(mins => s.dur))::time
      FROM (
            SELECT g.ini, j.duracion_bloque_min::int AS dur
              FROM jornada j
              JOIN medico m ON m.persona_id = j.medico_id AND m.estado = 'ACTIVO'
             CROSS JOIN LATERAL generate_series(
                         p_fecha + j.hora_inicio,
                         p_fecha + j.hora_fin - make_interval(mins => j.duracion_bloque_min::int),
                         make_interval(mins => j.duracion_bloque_min::int)) AS g(ini)
             WHERE j.medico_id = p_medico AND j.vigente
               AND j.dia_semana = EXTRACT(ISODOW FROM p_fecha)
           ) s
     WHERE (p_fecha > current_date OR (p_fecha = current_date AND s.ini > localtimestamp))
       AND NOT EXISTS (SELECT 1 FROM cita c
                        WHERE c.medico_id = p_medico AND c.estado IN ('PROGRAMADA', 'CONFIRMADA')
                          AND tsrange(c.fecha + c.hora_inicio, c.fecha + c.hora_fin)
                              && tsrange(s.ini, s.ini + make_interval(mins => s.dur)))
       AND NOT EXISTS (SELECT 1 FROM bloqueo_agenda b
                        WHERE b.medico_id = p_medico
                          AND tsrange(b.fecha_inicio, b.fecha_fin)
                              && tsrange(s.ini, s.ini + make_interval(mins => s.dur)))
     ORDER BY s.ini;
$$;

-- ---------- CU14: reglas que valida la base al registrar una cita ---------------------
CREATE OR REPLACE FUNCTION tg_validar_cita() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.fecha + NEW.hora_inicio < localtimestamp THEN
        RAISE EXCEPTION 'No se puede agendar una cita en una fecha u hora pasada';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM paciente WHERE persona_id = NEW.paciente_id AND estado = 'ACTIVO') THEN
        RAISE EXCEPTION 'El paciente seleccionado no está activo';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM medico WHERE persona_id = NEW.medico_id AND estado = 'ACTIVO') THEN
        RAISE EXCEPTION 'El médico seleccionado no está activo';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM fn_especialidades_permitidas(NEW.paciente_id, NEW.fecha) f
                    WHERE f.id = NEW.especialidad_id) THEN
        RAISE EXCEPTION 'La especialidad seleccionada no corresponde a la edad o sexo del paciente';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_validar_cita
    BEFORE INSERT ON cita
    FOR EACH ROW EXECUTE FUNCTION tg_validar_cita();

-- ---------- Historial de estados de la cita -------------------------------------------
-- El código informa quién actúa con set_config('app.usuario_id', ...) dentro de la transacción.
CREATE OR REPLACE FUNCTION tg_historial_estado_cita() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_usuario BIGINT := NULLIF(current_setting('app.usuario_id', TRUE), '')::BIGINT;
BEGIN
    IF TG_OP = 'INSERT' THEN
        INSERT INTO historial_estado_cita (cita_id, estado_anterior, estado_nuevo, usuario_id)
        VALUES (NEW.id, NULL, NEW.estado, COALESCE(v_usuario, NEW.registrado_por));
    ELSIF NEW.estado IS DISTINCT FROM OLD.estado THEN
        INSERT INTO historial_estado_cita (cita_id, estado_anterior, estado_nuevo, usuario_id)
        VALUES (NEW.id, OLD.estado, NEW.estado, v_usuario);
    ELSE
        RETURN NEW;
    END IF;
    -- bandeja de eventos del paciente (la leerán las notificaciones y el asistente virtual)
    INSERT INTO evento_paciente (paciente_id, tipo_evento, payload)
    VALUES (NEW.paciente_id, 'CITA_' || NEW.estado,
            jsonb_build_object('cita_id', NEW.id, 'codigo', NEW.codigo, 'estado', NEW.estado,
                               'fecha', NEW.fecha, 'hora_inicio', NEW.hora_inicio, 'medico_id', NEW.medico_id));
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_historial_estado_cita
    AFTER INSERT OR UPDATE OF estado ON cita
    FOR EACH ROW EXECUTE FUNCTION tg_historial_estado_cita();

-- ---------- La ficha clínica se actualiza con fecha ------------------------------------
CREATE OR REPLACE FUNCTION tg_ficha_fecha() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    NEW.fecha_actualizacion := now();
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_ficha_fecha
    BEFORE UPDATE ON ficha_clinica
    FOR EACH ROW EXECUTE FUNCTION tg_ficha_fecha();

-- ---------- Un solo comprobante por pago pagado ---------------------------------------
CREATE OR REPLACE FUNCTION tg_comprobante_solo_pagado() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pago WHERE id = NEW.pago_id AND estado = 'PAGADO') THEN
        RAISE EXCEPTION 'Solo se emite comprobante para pagos en estado PAGADO';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_comprobante_solo_pagado
    BEFORE INSERT ON comprobante
    FOR EACH ROW EXECUTE FUNCTION tg_comprobante_solo_pagado();

-- ---------- Procedimientos almacenados para el resto del ciclo de vida de la cita -----
-- (los usarán los casos de uso del Ciclo 2: confirmar, cancelar, registrar asistencia)
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
    IF NOT (   (v_actual = 'PROGRAMADA' AND p_nuevo IN ('CONFIRMADA', 'CANCELADA'))
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

CREATE OR REPLACE FUNCTION sp_registrar_pago(p_cita BIGINT, p_metodo VARCHAR, p_usuario BIGINT, p_referencia VARCHAR DEFAULT NULL)
RETURNS VARCHAR                                          -- devuelve el nro. de comprobante
LANGUAGE plpgsql AS $$
DECLARE
    v_pago BIGINT;
    v_nro  VARCHAR;
BEGIN
    UPDATE pago SET estado = 'PAGADO', metodo = p_metodo, referencia_externa = p_referencia, fecha_pago = now()
     WHERE cita_id = p_cita AND estado = 'PENDIENTE'
     RETURNING id INTO v_pago;
    IF v_pago IS NULL THEN
        RAISE EXCEPTION 'La cita % no tiene un pago pendiente', p_cita;
    END IF;
    INSERT INTO comprobante (pago_id) VALUES (v_pago) RETURNING nro_correlativo INTO v_nro;
    PERFORM fn_registrar_bitacora(p_usuario, CASE WHEN p_metodo = 'STRIPE' THEN 'PAGO_APROBADO' ELSE 'PAGO_REGISTRADO' END, 'Pago de la cita ' || p_cita || ' (' || p_metodo || ')',
                                  NULL, 'pago', v_pago);
    RETURN v_nro;
END;
$$;

-- ======================= Exámenes de laboratorio =======================================
-- Solo se solicita un examen en una cita confirmada o atendida; el costo toma el del catálogo.
CREATE OR REPLACE FUNCTION tg_solicitud_examen_valida() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM cita WHERE id = NEW.cita_id AND estado IN ('CONFIRMADA', 'ATENDIDA')) THEN
        RAISE EXCEPTION 'Solo se solicitan exámenes en citas confirmadas o atendidas';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM tipo_examen WHERE id = NEW.tipo_examen_id AND estado) THEN
        RAISE EXCEPTION 'El tipo de examen seleccionado no está activo';
    END IF;
    NEW.costo := COALESCE(NEW.costo, (SELECT costo FROM tipo_examen WHERE id = NEW.tipo_examen_id));
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_solicitud_examen_valida
    BEFORE INSERT ON solicitud_examen
    FOR EACH ROW EXECUTE FUNCTION tg_solicitud_examen_valida();

-- Al cargar el resultado: la solicitud queda lista, se registra el evento y se avisa al paciente.
CREATE OR REPLACE FUNCTION tg_resultado_listo() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_paciente BIGINT;
    v_examen   VARCHAR;
BEGIN
    UPDATE solicitud_examen SET estado = 'RESULTADO_LISTO' WHERE id = NEW.solicitud_examen_id AND estado IN ('SOLICITADO', 'EN_PROCESO');
    SELECT c.paciente_id, t.nombre INTO v_paciente, v_examen
      FROM solicitud_examen s JOIN cita c ON c.id = s.cita_id JOIN tipo_examen t ON t.id = s.tipo_examen_id
     WHERE s.id = NEW.solicitud_examen_id;
    INSERT INTO evento_paciente (paciente_id, tipo_evento, payload)
    VALUES (v_paciente, 'RESULTADO_LISTO', jsonb_build_object('solicitud_id', NEW.solicitud_examen_id, 'examen', v_examen));
    INSERT INTO notificacion (usuario_id, tipo, canal, asunto, mensaje)
    SELECT u.id, 'SISTEMA', 'CORREO', 'Resultado de laboratorio disponible',
           'Su resultado de ' || v_examen || ' ya está disponible en el sistema.'
      FROM usuario u WHERE u.persona_id = v_paciente;
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_resultado_listo
    AFTER INSERT ON resultado_laboratorio
    FOR EACH ROW EXECUTE FUNCTION tg_resultado_listo();

CREATE OR REPLACE FUNCTION sp_solicitar_examen(p_cita BIGINT, p_tipo SMALLINT, p_usuario BIGINT)
RETURNS BIGINT
LANGUAGE plpgsql AS $$
DECLARE
    v_id BIGINT;
BEGIN
    INSERT INTO solicitud_examen (cita_id, tipo_examen_id) VALUES (p_cita, p_tipo) RETURNING id INTO v_id;
    PERFORM fn_registrar_bitacora(p_usuario, 'SOLICITAR_EXAMEN', 'Examen solicitado en la cita ' || p_cita, NULL, 'solicitud_examen', v_id);
    RETURN v_id;
END;
$$;

-- ======================= Chat =========================================================
CREATE OR REPLACE FUNCTION tg_conversacion_cierre() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.estado = 'CERRADA' AND NEW.fecha_fin IS NULL THEN
        NEW.fecha_fin := now();
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_conversacion_cierre
    BEFORE UPDATE OF estado ON conversacion
    FOR EACH ROW EXECUTE FUNCTION tg_conversacion_cierre();

CREATE OR REPLACE FUNCTION tg_mensaje_conversacion_activa() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM conversacion WHERE id = NEW.conversacion_id AND estado = 'ACTIVA') THEN
        RAISE EXCEPTION 'La conversación está cerrada';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_mensaje_conversacion_activa
    BEFORE INSERT ON mensaje_chat
    FOR EACH ROW EXECUTE FUNCTION tg_mensaje_conversacion_activa();

CREATE OR REPLACE FUNCTION tg_faq_fecha() RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    NEW.fecha_actualizacion := now();
    RETURN NEW;
END;
$$;
CREATE TRIGGER tg_faq_fecha
    BEFORE UPDATE ON pregunta_frecuente
    FOR EACH ROW EXECUTE FUNCTION tg_faq_fecha();

-- Búsqueda simple de preguntas frecuentes por palabras (para el asistente virtual)
CREATE OR REPLACE FUNCTION fn_buscar_faq(p_texto TEXT)
RETURNS TABLE (id SMALLINT, categoria VARCHAR, pregunta VARCHAR, respuesta TEXT)
LANGUAGE sql STABLE AS $$
    SELECT f.id, f.categoria, f.pregunta, f.respuesta
      FROM pregunta_frecuente f
     WHERE f.estado
       AND (f.pregunta ILIKE '%' || p_texto || '%' OR f.palabras_clave ILIKE '%' || p_texto || '%')
     ORDER BY f.id;
$$;
