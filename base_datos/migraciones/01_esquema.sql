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
