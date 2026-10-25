-- ===== Clínica Vida Salud: BASE DE DATOS COMPLETA (esquema + funciones + triggers + datos mínimos + vistas + seguridad Supabase) =====
-- Pega este único archivo en el SQL Editor de Supabase (o ejecútalo con psql) sobre una base vacía.
-- Queda lista para usar: entra como admin@clinicavidasalud.com / Admin123* y crea todo lo demás desde el sistema.

-- ################ 01_esquema.sql ################
-- =====================================================================================
--  Clínica Vida Salud — Sistema Web de Gestión de Citas Médicas
--  01_esquema.sql : extensiones, secuencias, tablas, restricciones e índices (PostgreSQL 14+)
--  Funciona igual en PostgreSQL local y en Supabase (SQL Editor).
--  Orden: 01 → 02 → 03 → 04 → 05 (solo Supabase). Ejemplos opcionales: OPCIONAL_datos_de_ejemplo.sql
-- =====================================================================================
CREATE EXTENSION IF NOT EXISTS btree_gist;      -- necesaria para impedir horarios superpuestos (EXCLUDE)

-- ---------- Secuencias de códigos legibles ----------
CREATE SEQUENCE IF NOT EXISTS seq_expediente  START 1;   -- EXP-000001
CREATE SEQUENCE IF NOT EXISTS seq_cita_codigo START 1;   -- CIT-000001
CREATE SEQUENCE IF NOT EXISTS seq_comprobante START 1;   -- REC-2026-00001

-- ======================= SEGURIDAD (CU01, CU02, CU03, CU04) ===========================
CREATE TABLE persona (
    id               BIGSERIAL    PRIMARY KEY,
    ci               VARCHAR(20)  NOT NULL UNIQUE,
    nombres          VARCHAR(80)  NOT NULL,
    apellidos        VARCHAR(80)  NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    sexo             CHAR(1)      NOT NULL CHECK (sexo IN ('F', 'M')),
    telefono         VARCHAR(20),
    direccion        VARCHAR(200),
    fecha_registro   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE rol (
    id          SMALLSERIAL PRIMARY KEY,
    nombre      VARCHAR(30) NOT NULL UNIQUE,            -- ADMINISTRADOR, RECEPCIONISTA, MEDICO, PACIENTE
    descripcion VARCHAR(150),
    estado      BOOLEAN     NOT NULL DEFAULT TRUE
);

CREATE TABLE permiso (
    id     SMALLSERIAL PRIMARY KEY,
    modulo VARCHAR(40) NOT NULL,                        -- PACIENTES, CITAS, AGENDAS, SEGURIDAD, ESPECIALIDADES_MEDICOS
    accion CHAR(1)     NOT NULL CHECK (accion IN ('C', 'R', 'M', 'A')),   -- Consultar, Registrar, Modificar, Activar/desactivar
    UNIQUE (modulo, accion)
);

CREATE TABLE rol_permiso (
    rol_id     SMALLINT NOT NULL REFERENCES rol(id)     ON DELETE CASCADE,
    permiso_id SMALLINT NOT NULL REFERENCES permiso(id) ON DELETE CASCADE,
    PRIMARY KEY (rol_id, permiso_id)
);

CREATE TABLE usuario (
    id                      BIGSERIAL    PRIMARY KEY,
    persona_id              BIGINT       NOT NULL UNIQUE REFERENCES persona(id),
    rol_id                  SMALLINT     NOT NULL REFERENCES rol(id),
    correo                  VARCHAR(120) NOT NULL,
    contrasena_hash         VARCHAR(255) NOT NULL,
    estado                  VARCHAR(10)  NOT NULL DEFAULT 'HABILITADA' CHECK (estado IN ('HABILITADA', 'SUSPENDIDA')),
    intentos_fallidos       INTEGER      NOT NULL DEFAULT 0,
    bloqueado_hasta         TIMESTAMPTZ,
    ultimo_acceso           TIMESTAMPTZ,
    debe_cambiar_contrasena BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_creacion          TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_usuario_correo ON usuario (lower(correo));

CREATE TABLE token_recuperacion (
    id               BIGSERIAL   PRIMARY KEY,
    usuario_id       BIGINT      NOT NULL REFERENCES usuario(id) ON DELETE CASCADE,
    token_hash       VARCHAR(64) NOT NULL,
    fecha_expiracion TIMESTAMPTZ NOT NULL,
    usado            BOOLEAN     NOT NULL DEFAULT FALSE,
    fecha_creacion   TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_token_recuperacion_hash ON token_recuperacion (token_hash);

CREATE TABLE parametro_sistema (
    clave       VARCHAR(60) PRIMARY KEY,
    valor       TEXT        NOT NULL,
    descripcion VARCHAR(200)
);

CREATE TABLE bitacora (                                 -- solo inserción (RN14): ver trigger tg_bitacora_solo_insercion
    id          BIGSERIAL    PRIMARY KEY,
    usuario_id  BIGINT       REFERENCES usuario(id),
    accion      VARCHAR(40)  NOT NULL,
    descripcion TEXT         NOT NULL,
    tabla       VARCHAR(40),
    registro_id BIGINT,
    ip          INET,
    fecha_hora  TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX idx_bitacora_fecha   ON bitacora (fecha_hora DESC);
CREATE INDEX idx_bitacora_usuario ON bitacora (usuario_id);
CREATE INDEX idx_bitacora_accion  ON bitacora (accion);

-- ======================= PACIENTES (CU05, CU06) =======================================
CREATE TABLE aseguradora (
    id     SMALLSERIAL  PRIMARY KEY,
    nombre VARCHAR(80)  NOT NULL UNIQUE,
    estado BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE paciente (
    persona_id                     BIGINT      PRIMARY KEY REFERENCES persona(id),
    nro_expediente                 VARCHAR(20) NOT NULL UNIQUE
                                   DEFAULT ('EXP-' || lpad(nextval('seq_expediente')::text, 6, '0')),
    contacto_emergencia_nombre     VARCHAR(100),
    contacto_emergencia_parentesco VARCHAR(40),
    contacto_emergencia_telefono   VARCHAR(20),
    aseguradora_id                 SMALLINT    REFERENCES aseguradora(id),
    nro_poliza                     VARCHAR(40),
    estado                         VARCHAR(10) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO', 'INACTIVO')),
    fecha_registro                 TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE ficha_clinica (                            -- composición 1..1 con paciente
    paciente_id              BIGINT       PRIMARY KEY REFERENCES paciente(persona_id) ON DELETE CASCADE,
    tipo_sangre              VARCHAR(20)  NOT NULL DEFAULT 'No especificado'
                             CHECK (tipo_sangre IN ('No especificado','A+','A-','B+','B-','AB+','AB-','O+','O-')),
    alergias                 TEXT         NOT NULL DEFAULT 'No especificado',
    enfermedades_cronicas    TEXT         NOT NULL DEFAULT 'No especificado',
    antecedentes_familiares  TEXT         NOT NULL DEFAULT 'No especificado',
    antecedentes_quirurgicos TEXT         NOT NULL DEFAULT 'No especificado',
    fecha_actualizacion      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ======================= CATÁLOGO MÉDICO (CU08, CU09, CU11) ===========================
CREATE TABLE especialidad (
    id             SMALLSERIAL   PRIMARY KEY,
    nombre         VARCHAR(80)   NOT NULL,
    descripcion    VARCHAR(250),
    costo_consulta NUMERIC(10,2) NOT NULL CHECK (costo_consulta > 0),
    edad_minima    SMALLINT      NOT NULL DEFAULT 0 CHECK (edad_minima >= 0),
    edad_maxima    SMALLINT      CHECK (edad_maxima IS NULL OR edad_maxima >= edad_minima),
    sexo_permitido CHAR(1)       NOT NULL DEFAULT 'A' CHECK (sexo_permitido IN ('A', 'F', 'M')),
    estado         BOOLEAN       NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX uq_especialidad_nombre ON especialidad (lower(nombre));

CREATE TABLE medico (
    persona_id BIGINT      PRIMARY KEY REFERENCES persona(id),
    matricula  VARCHAR(30) NOT NULL,
    estado     VARCHAR(10) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO', 'INACTIVO')),
    fecha_alta TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_medico_matricula ON medico (lower(matricula));

CREATE TABLE medico_especialidad (
    medico_id       BIGINT   NOT NULL REFERENCES medico(persona_id) ON DELETE CASCADE,
    especialidad_id SMALLINT NOT NULL REFERENCES especialidad(id),
    PRIMARY KEY (medico_id, especialidad_id)
);

CREATE TABLE jornada (                                  -- horario semanal del médico (una fila por día de atención)
    id                  BIGSERIAL PRIMARY KEY,
    medico_id           BIGINT    NOT NULL REFERENCES medico(persona_id) ON DELETE CASCADE,
    dia_semana          SMALLINT  NOT NULL CHECK (dia_semana BETWEEN 1 AND 7),     -- 1 = lunes ... 7 = domingo (ISO)
    hora_inicio         TIME      NOT NULL,
    hora_fin            TIME      NOT NULL,
    duracion_bloque_min SMALLINT  NOT NULL DEFAULT 30 CHECK (duracion_bloque_min BETWEEN 5 AND 240),
    vigente             BOOLEAN   NOT NULL DEFAULT TRUE,
    fecha_creacion      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ck_jornada_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT ex_jornada_superpuesta EXCLUDE USING gist (
        medico_id  WITH =,
        dia_semana WITH =,
        tsrange(DATE '2000-01-01' + hora_inicio, DATE '2000-01-01' + hora_fin) WITH &&
    ) WHERE (vigente)
);
CREATE INDEX idx_jornada_medico ON jornada (medico_id, vigente);

CREATE TABLE bloqueo_agenda (                           -- vacaciones, permisos, reuniones del médico
    id           BIGSERIAL    PRIMARY KEY,
    medico_id    BIGINT       NOT NULL REFERENCES medico(persona_id) ON DELETE CASCADE,
    fecha_inicio TIMESTAMP    NOT NULL,
    fecha_fin    TIMESTAMP    NOT NULL,
    tipo_bloqueo VARCHAR(20)  NOT NULL DEFAULT 'OTRO' CHECK (tipo_bloqueo IN ('VACACIONES', 'PERMISO', 'REUNION', 'OTRO')),
    motivo       VARCHAR(200),
    CONSTRAINT ck_bloqueo_fechas CHECK (fecha_fin > fecha_inicio)
);
CREATE INDEX idx_bloqueo_medico ON bloqueo_agenda (medico_id, fecha_inicio);

-- ======================= CITAS Y PAGOS (CU13, CU14, CU19) =============================
CREATE TABLE cita (
    id              BIGSERIAL    PRIMARY KEY,
    codigo          VARCHAR(20)  NOT NULL UNIQUE
                    DEFAULT ('CIT-' || lpad(nextval('seq_cita_codigo')::text, 6, '0')),
    paciente_id     BIGINT       NOT NULL REFERENCES paciente(persona_id),
    medico_id       BIGINT       NOT NULL,
    especialidad_id SMALLINT     NOT NULL,
    fecha           DATE         NOT NULL,
    hora_inicio     TIME         NOT NULL,
    hora_fin        TIME         NOT NULL,
    estado          VARCHAR(12)  NOT NULL DEFAULT 'PROGRAMADA'
                    CHECK (estado IN ('PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA')),
    motivo_consulta VARCHAR(300),
    origen          VARCHAR(12)  NOT NULL DEFAULT 'RECEPCION' CHECK (origen IN ('RECEPCION', 'MEDICO', 'PORTAL')),
    registrado_por  BIGINT       NOT NULL REFERENCES usuario(id),
    fecha_registro  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT ck_cita_horas CHECK (hora_fin > hora_inicio),
    -- el médico debe ejercer esa especialidad (impide borrar una especialidad usada en citas)
    CONSTRAINT fk_cita_medico_especialidad FOREIGN KEY (medico_id, especialidad_id)
        REFERENCES medico_especialidad (medico_id, especialidad_id),
    -- RN: un médico no puede tener dos citas activas superpuestas
    CONSTRAINT ex_cita_medico EXCLUDE USING gist (
        medico_id WITH =,
        tsrange(fecha + hora_inicio, fecha + hora_fin) WITH &&
    ) WHERE (estado IN ('PROGRAMADA', 'CONFIRMADA')),
    -- RN: un paciente no puede tener dos citas activas superpuestas
    CONSTRAINT ex_cita_paciente EXCLUDE USING gist (
        paciente_id WITH =,
        tsrange(fecha + hora_inicio, fecha + hora_fin) WITH &&
    ) WHERE (estado IN ('PROGRAMADA', 'CONFIRMADA'))
);
CREATE INDEX idx_cita_fecha_medico ON cita (fecha, medico_id);
CREATE INDEX idx_cita_paciente     ON cita (paciente_id, fecha);
CREATE INDEX idx_cita_especialidad ON cita (especialidad_id, estado);

CREATE TABLE historial_estado_cita (
    id              BIGSERIAL   PRIMARY KEY,
    cita_id         BIGINT      NOT NULL REFERENCES cita(id) ON DELETE CASCADE,
    estado_anterior VARCHAR(12),
    estado_nuevo    VARCHAR(12) NOT NULL,
    usuario_id      BIGINT      REFERENCES usuario(id),
    fecha_hora      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_historial_cita ON historial_estado_cita (cita_id, fecha_hora);

CREATE TABLE pago (
    id                 BIGSERIAL     PRIMARY KEY,
    cita_id            BIGINT        NOT NULL UNIQUE REFERENCES cita(id) ON DELETE CASCADE,   -- Cita 1 — genera — 0..1 Pago
    monto              NUMERIC(10,2) NOT NULL CHECK (monto >= 0),
    moneda             CHAR(3)       NOT NULL DEFAULT 'BOB',
    estado             VARCHAR(12)   NOT NULL DEFAULT 'PENDIENTE' CHECK (estado IN ('PENDIENTE', 'PAGADO', 'ANULADO', 'REEMBOLSADO')),
    metodo             VARCHAR(15)   CHECK (metodo IN ('EFECTIVO', 'TARJETA', 'QR', 'TRANSFERENCIA', 'STRIPE', 'SEGURO')),
    referencia_externa VARCHAR(100),                    -- p. ej. id de la sesión de Stripe (CU19)
    registrado_por     BIGINT        NOT NULL REFERENCES usuario(id),
    fecha_registro     TIMESTAMPTZ   NOT NULL DEFAULT now(),
    fecha_pago         TIMESTAMPTZ
);
CREATE INDEX idx_pago_estado ON pago (estado);

CREATE TABLE comprobante (
    id             BIGSERIAL   PRIMARY KEY,
    pago_id        BIGINT      NOT NULL UNIQUE REFERENCES pago(id) ON DELETE CASCADE,        -- Pago 1 — emite — 0..1 Comprobante
    nro_correlativo VARCHAR(20) NOT NULL UNIQUE DEFAULT ('REC-' || to_char(now(), 'YYYY') || '-' || lpad(nextval('seq_comprobante')::text, 5, '0')),
    fecha_emision  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE notificacion (
    id             BIGSERIAL    PRIMARY KEY,
    usuario_id     BIGINT       NOT NULL REFERENCES usuario(id) ON DELETE CASCADE,
    cita_id        BIGINT       REFERENCES cita(id) ON DELETE SET NULL,
    tipo           VARCHAR(20)  NOT NULL CHECK (tipo IN ('RECORDATORIO', 'CONFIRMACION', 'CANCELACION', 'SISTEMA')),
    canal          VARCHAR(10)  NOT NULL DEFAULT 'CORREO' CHECK (canal IN ('CORREO', 'PANTALLA')),
    asunto         VARCHAR(120) NOT NULL,
    mensaje        TEXT         NOT NULL,
    leida          BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_creacion TIMESTAMPTZ  NOT NULL DEFAULT now(),
    fecha_envio    TIMESTAMPTZ
);
CREATE INDEX idx_notificacion_usuario ON notificacion (usuario_id, leida);

-- ======================= VÍNCULOS FAMILIARES, EVENTOS (Ciclo 2) =======================
CREATE TABLE vinculo_familiar (                         -- el titular puede gestionar citas de sus familiares
    id             BIGSERIAL   PRIMARY KEY,
    paciente_id    BIGINT      NOT NULL REFERENCES paciente(persona_id) ON DELETE CASCADE,   -- titular
    familiar_id    BIGINT      NOT NULL REFERENCES paciente(persona_id) ON DELETE CASCADE,
    parentesco     VARCHAR(10) NOT NULL CHECK (parentesco IN ('HIJO', 'PADRE', 'MADRE', 'CONYUGE', 'HERMANO', 'OTRO')),
    fecha_vinculo  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ck_vinculo_distintos CHECK (paciente_id <> familiar_id),
    CONSTRAINT uq_vinculo UNIQUE (paciente_id, familiar_id)
);

CREATE TABLE evento_paciente (                          -- bandeja de eventos (cola) para notificaciones y chat
    id           BIGSERIAL    PRIMARY KEY,
    paciente_id  BIGINT       NOT NULL REFERENCES paciente(persona_id) ON DELETE CASCADE,
    tipo_evento  VARCHAR(40)  NOT NULL,                 -- p. ej. CITA_PROGRAMADA, CITA_CANCELADA, RESULTADO_LISTO
    payload      JSONB        NOT NULL DEFAULT '{}'::jsonb,
    procesado    BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_evento TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX idx_evento_pendiente ON evento_paciente (procesado, fecha_evento);

-- ======================= EXÁMENES DE LABORATORIO (Ciclo 2) ============================
CREATE TABLE tipo_examen (
    id          SMALLSERIAL   PRIMARY KEY,
    nombre      VARCHAR(80)   NOT NULL,
    descripcion VARCHAR(250),
    costo       NUMERIC(10,2) NOT NULL CHECK (costo >= 0),
    estado      BOOLEAN       NOT NULL DEFAULT TRUE
);
CREATE UNIQUE INDEX uq_tipo_examen_nombre ON tipo_examen (lower(nombre));

CREATE TABLE solicitud_examen (
    id               BIGSERIAL     PRIMARY KEY,
    cita_id          BIGINT        NOT NULL REFERENCES cita(id),
    tipo_examen_id   SMALLINT      NOT NULL REFERENCES tipo_examen(id),
    costo            NUMERIC(10,2) CHECK (costo >= 0),
    estado           VARCHAR(16)   NOT NULL DEFAULT 'SOLICITADO'
                     CHECK (estado IN ('SOLICITADO', 'EN_PROCESO', 'RESULTADO_LISTO', 'ENTREGADO', 'CANCELADO')),
    fecha_solicitud  TIMESTAMPTZ   NOT NULL DEFAULT now()
);
CREATE INDEX idx_solicitud_cita   ON solicitud_examen (cita_id);
CREATE INDEX idx_solicitud_estado ON solicitud_examen (estado);

CREATE TABLE resultado_laboratorio (
    id                   BIGSERIAL    PRIMARY KEY,
    solicitud_examen_id  BIGINT       NOT NULL UNIQUE REFERENCES solicitud_examen(id) ON DELETE CASCADE,
    resultado            TEXT,
    archivo_nombre       VARCHAR(150),
    tipo_archivo         VARCHAR(10)  CHECK (tipo_archivo IN ('PDF', 'JPG', 'PNG')),
    tamano_bytes         INTEGER      CHECK (tamano_bytes IS NULL OR tamano_bytes > 0),
    fecha_resultado      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ======================= ASISTENTE VIRTUAL / CHAT (Ciclo 2) ===========================
CREATE TABLE conversacion (
    id           UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
    paciente_id  BIGINT      REFERENCES paciente(persona_id) ON DELETE SET NULL,   -- 0..1: puede ser un visitante
    ip           INET,
    estado       VARCHAR(10) NOT NULL DEFAULT 'ACTIVA' CHECK (estado IN ('ACTIVA', 'CERRADA')),
    fecha_inicio TIMESTAMPTZ NOT NULL DEFAULT now(),
    fecha_fin    TIMESTAMPTZ
);
CREATE INDEX idx_conversacion_paciente ON conversacion (paciente_id);

CREATE TABLE mensaje_chat (
    id              BIGSERIAL   PRIMARY KEY,
    conversacion_id UUID        NOT NULL REFERENCES conversacion(id) ON DELETE CASCADE,
    emisor          VARCHAR(10) NOT NULL CHECK (emisor IN ('PACIENTE', 'ASISTENTE')),
    texto           TEXT        NOT NULL,
    intencion       VARCHAR(30),                         -- intención detectada (AGENDAR, CONSULTAR_CITA, FAQ...)
    fecha_hora      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_mensaje_conversacion ON mensaje_chat (conversacion_id, fecha_hora);

CREATE TABLE pregunta_frecuente (
    id                  SMALLSERIAL  PRIMARY KEY,
    categoria           VARCHAR(10)  NOT NULL CHECK (categoria IN ('CITAS', 'PAGOS', 'EXAMENES', 'CUENTA', 'GENERAL')),
    pregunta            VARCHAR(250) NOT NULL,
    respuesta           TEXT         NOT NULL,
    palabras_clave      VARCHAR(250),
    estado              BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_actualizacion TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ################ 02_funciones_triggers.sql ################
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

-- ################ 03_datos_base.sql ################
-- =====================================================================================
--  03_datos_base.sql : datos mínimos para que el sistema funcione (roles, permisos,
--  parámetros y el administrador inicial. Todo lo demás (especialidades, médicos, pacientes, citas...) se crea desde el sistema.)
--  Administrador inicial:  admin@clinicavidasalud.com  /  Admin123*   (pide cambiar la clave al entrar)
-- =====================================================================================
INSERT INTO rol (nombre, descripcion) VALUES
 ('ADMINISTRADOR', 'Control total del sistema'),
 ('RECEPCIONISTA', 'Registra pacientes y agenda citas'),
 ('MEDICO',        'Consulta su agenda y sus pacientes'),
 ('PACIENTE',      'Agenda sus propias citas desde el portal');

-- Acciones: C = consultar, R = registrar, M = modificar, A = activar/desactivar
INSERT INTO permiso (modulo, accion) VALUES
 ('PACIENTES','C'),('PACIENTES','R'),('PACIENTES','M'),
 ('ESPECIALIDADES_MEDICOS','C'),('ESPECIALIDADES_MEDICOS','R'),('ESPECIALIDADES_MEDICOS','M'),('ESPECIALIDADES_MEDICOS','A'),
 ('AGENDAS','C'),('AGENDAS','M'),
 ('CITAS','C'),('CITAS','R'),
 ('SEGURIDAD','C'),('SEGURIDAD','R'),('SEGURIDAD','M'),('SEGURIDAD','A'),
 -- módulos del Ciclo 2 (el código del Ciclo 1 aún no los usa)
 ('EXAMENES','C'),('EXAMENES','R'),('EXAMENES','M'),('PAGOS','C'),('PAGOS','R'),('REPORTES','C'),('COMUNICACION','C'),('COMUNICACION','M');

-- ADMINISTRADOR: todos los permisos
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r CROSS JOIN permiso p WHERE r.nombre = 'ADMINISTRADOR';
-- RECEPCIONISTA
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('PACIENTES','C'),('PACIENTES','R'),('PACIENTES','M'),('CITAS','C'),('CITAS','R'),
                              ('ESPECIALIDADES_MEDICOS','C'),('AGENDAS','C'),('PAGOS','C'),('PAGOS','R'),('EXAMENES','C'),('REPORTES','C'))
 WHERE r.nombre = 'RECEPCIONISTA';
-- MEDICO
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('PACIENTES','C'),('CITAS','C'),('CITAS','R'),('ESPECIALIDADES_MEDICOS','C'),('AGENDAS','C'),('EXAMENES','C'),('EXAMENES','R'),('EXAMENES','M'))
 WHERE r.nombre = 'MEDICO';
-- PACIENTE
INSERT INTO rol_permiso SELECT r.id, p.id FROM rol r JOIN permiso p
  ON (p.modulo, p.accion) IN (('CITAS','C'),('CITAS','R'),('PAGOS','C'),('PAGOS','R'),('EXAMENES','C'),('COMUNICACION','C'))
 WHERE r.nombre = 'PACIENTE';

INSERT INTO parametro_sistema (clave, valor, descripcion) VALUES
 ('INTENTOS_MAX_LOGIN',   '3',  'Intentos fallidos antes de bloquear la cuenta'),
 ('MINUTOS_BLOQUEO',      '15', 'Minutos que dura el bloqueo temporal de la cuenta'),
 ('MINUTOS_ENLACE_RECUP', '30', 'Minutos de validez del enlace para recuperar contraseña');

-- Administrador inicial
WITH p AS (
  INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion)
  VALUES ('1000001', 'Administrador', 'del Sistema', DATE '1985-03-15', 'M', '70000001', 'Clínica Vida Salud')
  RETURNING id)
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, debe_cambiar_contrasena)
SELECT p.id, (SELECT id FROM rol WHERE nombre = 'ADMINISTRADOR'), 'admin@clinicavidasalud.com',
       '$2y$10$EZzm0CFl83q5MiQmI6S/D.sROJR0GzMQUvrGGblizusuFnGgQ31Ui', TRUE
  FROM p;

-- ################ 04_vistas.sql ################
-- =====================================================================================
--  04_vistas.sql : vistas de consulta listas para pantallas y reportes
--  (los casos de uso pueden hacer SELECT directo a estas vistas)
-- =====================================================================================

-- Cita con todos sus nombres (constancias, listados, agenda)
CREATE OR REPLACE VIEW vw_citas_detalle AS
SELECT c.id, c.codigo, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.origen, c.motivo_consulta,
       c.paciente_id, pp.ci AS paciente_ci, pp.nombres || ' ' || pp.apellidos AS paciente, pa.nro_expediente,
       c.medico_id, pm.nombres || ' ' || pm.apellidos AS medico, m.matricula,
       c.especialidad_id, e.nombre AS especialidad, e.costo_consulta,
       pg.estado AS pago_estado, pg.monto, pg.metodo
  FROM cita c
  JOIN paciente pa     ON pa.persona_id = c.paciente_id
  JOIN persona pp      ON pp.id = c.paciente_id
  JOIN medico m        ON m.persona_id = c.medico_id
  JOIN persona pm      ON pm.id = c.medico_id
  JOIN especialidad e  ON e.id = c.especialidad_id
  LEFT JOIN pago pg    ON pg.cita_id = c.id;

-- Agenda activa de cada médico (hoy en adelante)
CREATE OR REPLACE VIEW vw_agenda_medico AS
SELECT medico_id, medico, fecha, hora_inicio, hora_fin, estado, codigo, paciente, paciente_ci, especialidad, motivo_consulta
  FROM vw_citas_detalle
 WHERE estado IN ('PROGRAMADA', 'CONFIRMADA') AND fecha >= current_date
 ORDER BY medico, fecha, hora_inicio;

-- Pagos que aún no se cobran (citas activas)
CREATE OR REPLACE VIEW vw_pagos_pendientes AS
SELECT pg.id AS pago_id, c.codigo, c.fecha, c.hora_inicio, c.estado AS estado_cita, pg.monto, pg.moneda,
       pp.nombres || ' ' || pp.apellidos AS paciente, pp.telefono
  FROM pago pg
  JOIN cita c     ON c.id = pg.cita_id
  JOIN persona pp ON pp.id = c.paciente_id
 WHERE pg.estado = 'PENDIENTE' AND c.estado IN ('PROGRAMADA', 'CONFIRMADA');

-- Resumen por paciente
CREATE OR REPLACE VIEW vw_resumen_paciente AS
SELECT pa.persona_id, pa.nro_expediente, pe.ci, pe.nombres || ' ' || pe.apellidos AS paciente, pe.sexo,
       date_part('year', age(pe.fecha_nacimiento))::int AS edad, pa.estado, a.nombre AS aseguradora,
       count(c.id)                                                      AS citas_total,
       count(c.id) FILTER (WHERE c.estado = 'ATENDIDA')                 AS citas_atendidas,
       count(c.id) FILTER (WHERE c.estado = 'INASISTENCIA')             AS inasistencias,
       count(c.id) FILTER (WHERE c.estado IN ('PROGRAMADA','CONFIRMADA') AND c.fecha >= current_date) AS citas_proximas,
       max(c.fecha) FILTER (WHERE c.estado = 'ATENDIDA')                AS ultima_atencion
  FROM paciente pa
  JOIN persona pe ON pe.id = pa.persona_id
  LEFT JOIN aseguradora a ON a.id = pa.aseguradora_id
  LEFT JOIN cita c ON c.paciente_id = pa.persona_id
 GROUP BY pa.persona_id, pa.nro_expediente, pe.ci, pe.nombres, pe.apellidos, pe.sexo, pe.fecha_nacimiento, pa.estado, a.nombre;

-- Productividad del médico en los últimos 30 días
CREATE OR REPLACE VIEW vw_productividad_medico AS
SELECT m.persona_id AS medico_id, p.nombres || ' ' || p.apellidos AS medico,
       count(c.id)                                          AS citas,
       count(c.id) FILTER (WHERE c.estado = 'ATENDIDA')     AS atendidas,
       count(c.id) FILTER (WHERE c.estado = 'CANCELADA')    AS canceladas,
       count(c.id) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias,
       round(100.0 * count(c.id) FILTER (WHERE c.estado = 'INASISTENCIA') / NULLIF(count(c.id), 0), 1) AS pct_inasistencia
  FROM medico m
  JOIN persona p ON p.id = m.persona_id
  LEFT JOIN cita c ON c.medico_id = m.persona_id AND c.fecha BETWEEN current_date - 30 AND current_date
 GROUP BY m.persona_id, p.nombres, p.apellidos;

-- Ingresos por día y método de pago
CREATE OR REPLACE VIEW vw_ingresos_diarios AS
SELECT fecha_pago::date AS dia, metodo, count(*) AS pagos, sum(monto) AS total
  FROM pago WHERE estado = 'PAGADO' GROUP BY fecha_pago::date, metodo ORDER BY dia DESC, metodo;

-- Exámenes en curso o con resultado por entregar
CREATE OR REPLACE VIEW vw_examenes_pendientes AS
SELECT s.id AS solicitud_id, s.estado, s.fecha_solicitud, t.nombre AS examen, c.codigo AS cita,
       pp.nombres || ' ' || pp.apellidos AS paciente, pm.nombres || ' ' || pm.apellidos AS medico
  FROM solicitud_examen s
  JOIN tipo_examen t ON t.id = s.tipo_examen_id
  JOIN cita c        ON c.id = s.cita_id
  JOIN persona pp    ON pp.id = c.paciente_id
  JOIN persona pm    ON pm.id = c.medico_id
 WHERE s.estado IN ('SOLICITADO', 'EN_PROCESO', 'RESULTADO_LISTO');

-- Cola de eventos sin procesar
CREATE OR REPLACE VIEW vw_eventos_pendientes AS
SELECT e.id, e.tipo_evento, e.fecha_evento, pe.nombres || ' ' || pe.apellidos AS paciente, e.payload
  FROM evento_paciente e JOIN persona pe ON pe.id = e.paciente_id
 WHERE NOT e.procesado ORDER BY e.fecha_evento;

-- ################ 05_supabase_seguridad.sql ################
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

