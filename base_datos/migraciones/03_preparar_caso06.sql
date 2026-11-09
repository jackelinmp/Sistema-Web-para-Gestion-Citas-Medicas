-- =====================================================================================
--  Clínica Vida Salud — Preparar la demostración del caso de prueba 06 (CU15 y CU21)
--  Ejecutar en Supabase (SQL Editor) el mismo día de la prueba, poco antes de hacerla.
--  Se puede ejecutar las veces que se quiera: solo crea lo que falta.
--
--  Deja en la agenda del Dr. Carlos Alberto Rojas Vargas (CI 3000001):
--    1) una cita CONFIRMADA (pagada) de HOY cuya hora ya pasó   -> «Marcar atendida» funciona;
--    2) una cita CONFIRMADA (pagada) de un día futuro           -> «Marcar atendida» se rechaza.
--  Paciente: Lucía Gómez Torrez (CI 5000002), Medicina General. Las fechas y horas se toman
--  en la hora de Bolivia (America/La_Paz), que es la que usa el sistema.
--  También asigna al rol MÉDICO el permiso «Pacientes – Modificar» (necesario para guardar la ficha).
-- =====================================================================================
DO $$
DECLARE
    v_hoy   DATE := (now() AT TIME ZONE 'America/La_Paz')::date;
    v_ahora TIME := (now() AT TIME ZONE 'America/La_Paz')::time;
    v_med   BIGINT;
    v_pac   BIGINT;
    v_esp   SMALLINT;
    v_rec   BIGINT;
    v_ini   TIME;
    v_fecha DATE;
    v_cita  BIGINT;
    v_i     INT;
BEGIN
    SELECT m.persona_id INTO v_med FROM medico m JOIN persona p ON p.id = m.persona_id WHERE p.ci = '3000001';
    SELECT pa.persona_id INTO v_pac FROM paciente pa JOIN persona p ON p.id = pa.persona_id WHERE p.ci = '5000002';
    SELECT id INTO v_esp FROM especialidad WHERE nombre ILIKE 'medicina general';
    SELECT id INTO v_rec FROM usuario WHERE correo = 'maria.lopez@clinicavidasalud.com';
    IF v_med IS NULL OR v_pac IS NULL OR v_esp IS NULL OR v_rec IS NULL THEN
        RAISE EXCEPTION 'Faltan los datos de ejemplo: ejecute antes 01_estructura.sql y 02_poblacion.sql';
    END IF;
    PERFORM set_config('app.usuario_id', v_rec::text, TRUE);

    -- Permiso del médico para guardar la ficha clínica (caso 06)
    INSERT INTO rol_permiso (rol_id, permiso_id)
    SELECT r.id, pe.id FROM rol r, permiso pe
     WHERE r.nombre = 'MEDICO' AND pe.modulo = 'PACIENTES' AND pe.accion = 'M'
    ON CONFLICT DO NOTHING;

    -- 1) Cita de HOY cuya hora ya pasó
    IF NOT EXISTS (SELECT 1 FROM cita WHERE medico_id = v_med AND fecha = v_hoy AND estado = 'CONFIRMADA'
                                        AND hora_inicio <= v_ahora - interval '5 minutes') THEN
        -- bloque de 20 minutos que empezó hace al menos 20 minutos, sin choque con otra cita
        v_ini := date_trunc('hour', v_ahora) + make_interval(mins => (EXTRACT(MINUTE FROM v_ahora)::int / 20) * 20) - interval '20 minutes';
        FOR v_i IN 0..30 LOOP
            IF NOT EXISTS (SELECT 1 FROM cita
                            WHERE fecha = v_hoy AND estado IN ('PROGRAMADA', 'CONFIRMADA')
                              AND (medico_id = v_med OR paciente_id = v_pac)
                              AND hora_inicio < v_ini + interval '20 minutes' AND hora_fin > v_ini) THEN
                EXIT;
            END IF;
            v_ini := v_ini - interval '20 minutes';
        END LOOP;
        -- El trigger de la cita no admite horas pasadas al REGISTRARLA: se registra mañana a las 23:00
        -- (hora sin citas) y luego se mueve a hoy, como si se hubiera sacado antes.
        INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta,
                          origen, registrado_por, fecha_registro)
        VALUES (v_pac, v_med, v_esp, v_hoy + 1, time '23:00', time '23:20', 'Control de presión arterial',
                'RECEPCION', v_rec, now() - interval '1 day')
        RETURNING id INTO v_cita;
        UPDATE cita SET fecha = v_hoy, hora_inicio = v_ini, hora_fin = v_ini + interval '20 minutes' WHERE id = v_cita;
        INSERT INTO pago (cita_id, monto, estado, registrado_por, fecha_registro)
        SELECT v_cita, costo_consulta, 'PENDIENTE', v_rec, now() - interval '1 day' FROM especialidad WHERE id = v_esp;
        PERFORM sp_registrar_pago(v_cita, 'EFECTIVO', v_rec);
        PERFORM sp_cambiar_estado_cita(v_cita, 'CONFIRMADA', v_rec);
        RAISE NOTICE 'Cita de hoy creada: % a las %', (SELECT codigo FROM cita WHERE id = v_cita), v_ini;
    END IF;

    -- 2) Cita CONFIRMADA de un día futuro (primer cupo libre en los próximos días hábiles)
    IF NOT EXISTS (SELECT 1 FROM cita WHERE medico_id = v_med AND fecha > v_hoy AND estado = 'CONFIRMADA') THEN
        FOR v_i IN 1..7 LOOP
            v_fecha := v_hoy + v_i;
            SELECT c.hora_inicio INTO v_ini FROM fn_cupos_disponibles(v_med, v_fecha) c
             WHERE NOT EXISTS (SELECT 1 FROM cita x WHERE x.paciente_id = v_pac AND x.fecha = v_fecha
                                  AND x.estado IN ('PROGRAMADA', 'CONFIRMADA'))
             ORDER BY c.hora_inicio DESC LIMIT 1;
            EXIT WHEN v_ini IS NOT NULL;
        END LOOP;
        INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta,
                          origen, registrado_por)
        VALUES (v_pac, v_med, v_esp, v_fecha, v_ini, v_ini + interval '20 minutes', 'Control general', 'RECEPCION', v_rec)
        RETURNING id INTO v_cita;
        INSERT INTO pago (cita_id, monto, estado, registrado_por)
        SELECT v_cita, costo_consulta, 'PENDIENTE', v_rec FROM especialidad WHERE id = v_esp;
        PERFORM sp_registrar_pago(v_cita, 'EFECTIVO', v_rec);
        PERFORM sp_cambiar_estado_cita(v_cita, 'CONFIRMADA', v_rec);
        RAISE NOTICE 'Cita futura creada: % del % a las %', (SELECT codigo FROM cita WHERE id = v_cita), v_fecha, v_ini;
    END IF;
END $$;

-- Verificación: citas confirmadas del Dr. Rojas de hoy en adelante
SELECT c.codigo, c.fecha, to_char(c.hora_inicio, 'HH24:MI') AS hora, c.estado, p.estado AS pago,
       pe.nombres || ' ' || pe.apellidos AS paciente
  FROM cita c
  JOIN pago p ON p.cita_id = c.id
  JOIN persona pe ON pe.id = c.paciente_id
 WHERE c.medico_id = (SELECT m.persona_id FROM medico m JOIN persona x ON x.id = m.persona_id WHERE x.ci = '3000001')
   AND c.fecha >= (now() AT TIME ZONE 'America/La_Paz')::date AND c.estado = 'CONFIRMADA'
 ORDER BY c.fecha, c.hora_inicio
 LIMIT 5;
