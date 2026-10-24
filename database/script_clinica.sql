-- =====================================================================
--  SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS - CLÍNICA VIDA SALUD
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS btree_gist;  
CREATE EXTENSION IF NOT EXISTS pgcrypto;    

-- Rango de horas del día (para impedir jornadas superpuestas)
DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'rango_hora') THEN
    CREATE TYPE rango_hora AS RANGE (subtype = time);
  END IF;
END $$;

CREATE SEQUENCE IF NOT EXISTS seq_expediente;
CREATE SEQUENCE IF NOT EXISTS seq_comprobante;

-- =====================================================================
-- 1. SEGURIDAD, ACCESO Y AUDITORÍA
-- =====================================================================
CREATE TABLE IF NOT EXISTS rol (
  id          SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre      VARCHAR(30)  NOT NULL UNIQUE,
  descripcion VARCHAR(200),
  estado      BOOLEAN      NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS permiso (
  id      SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  modulo  VARCHAR(40) NOT NULL,
  accion  CHAR(1)     NOT NULL CHECK (accion IN ('C','R','M','A')),  
  UNIQUE (modulo, accion)
);

CREATE TABLE IF NOT EXISTS rol_permiso (               
  rol_id     SMALLINT NOT NULL REFERENCES rol(id) ON DELETE CASCADE,
  permiso_id SMALLINT NOT NULL REFERENCES permiso(id) ON DELETE CASCADE,
  PRIMARY KEY (rol_id, permiso_id)
);

CREATE TABLE IF NOT EXISTS persona (                 
  id               BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  ci               VARCHAR(15)  NOT NULL UNIQUE,
  nombres          VARCHAR(60)  NOT NULL,
  apellidos        VARCHAR(60)  NOT NULL,
  fecha_nacimiento DATE         NOT NULL CHECK (fecha_nacimiento > DATE '1900-01-01'),
  sexo             CHAR(1)      NOT NULL CHECK (sexo IN ('F','M')),
  telefono         VARCHAR(20),
  direccion        VARCHAR(150),
  fecha_registro   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS usuario (                  
  id                      BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  persona_id              BIGINT       NOT NULL UNIQUE REFERENCES persona(id),
  rol_id                  SMALLINT     NOT NULL REFERENCES rol(id),
  correo                  VARCHAR(120) NOT NULL,
  contrasena_hash         VARCHAR(255) NOT NULL,
  estado                  VARCHAR(12)  NOT NULL DEFAULT 'HABILITADA' CHECK (estado IN ('HABILITADA','SUSPENDIDA')),
  debe_cambiar_contrasena BOOLEAN      NOT NULL DEFAULT FALSE,
  intentos_fallidos       SMALLINT     NOT NULL DEFAULT 0,
  ultimo_acceso           TIMESTAMPTZ
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuario_correo ON usuario (lower(correo));

CREATE TABLE IF NOT EXISTS bitacora (                 
  id          BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fecha_hora  TIMESTAMPTZ NOT NULL DEFAULT now(),
  usuario_id  BIGINT REFERENCES usuario(id),          
  accion      VARCHAR(40) NOT NULL,                   
  descripcion TEXT        NOT NULL,
  tabla       VARCHAR(40),
  registro_id BIGINT,
  ip          INET                                  
);
CREATE INDEX IF NOT EXISTS ix_bitacora_fecha   ON bitacora (fecha_hora);
CREATE INDEX IF NOT EXISTS ix_bitacora_usuario ON bitacora (usuario_id);

-- =====================================================================
-- 2. PACIENTES Y PERFILES
-- =====================================================================
CREATE TABLE IF NOT EXISTS aseguradora (
  id     SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL UNIQUE,
  estado BOOLEAN     NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS paciente (                
  persona_id                    BIGINT PRIMARY KEY REFERENCES persona(id),
  nro_expediente                VARCHAR(12) NOT NULL UNIQUE
                                DEFAULT 'EXP-' || lpad(nextval('seq_expediente')::text, 6, '0'),
  contacto_emergencia_nombre    VARCHAR(100) NOT NULL,
  contacto_emergencia_parentesco VARCHAR(30) NOT NULL,
  contacto_emergencia_telefono  VARCHAR(20)  NOT NULL,
  aseguradora_id                SMALLINT REFERENCES aseguradora(id),
  nro_poliza                    VARCHAR(40),
  estado                        VARCHAR(10) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
  CHECK ((aseguradora_id IS NULL) = (nro_poliza IS NULL))
);

CREATE TABLE IF NOT EXISTS ficha_clinica (           
  paciente_id               BIGINT PRIMARY KEY REFERENCES paciente(persona_id) ON DELETE CASCADE,
  tipo_sangre               VARCHAR(15) NOT NULL DEFAULT 'NO_ESPECIFICADO'
                            CHECK (tipo_sangre IN ('A+','A-','B+','B-','AB+','AB-','O+','O-','NO_ESPECIFICADO')),
  alergias                  TEXT NOT NULL DEFAULT 'No especificado',
  enfermedades_cronicas     TEXT NOT NULL DEFAULT 'No especificado',
  antecedentes_familiares   TEXT NOT NULL DEFAULT 'No especificado',
  antecedentes_gineco_obst  TEXT,                  
  actualizado_por           BIGINT,                   
  fecha_actualizacion       TIMESTAMPTZ
);

CREATE TABLE IF NOT EXISTS vinculo_familiar (       
  responsable_id BIGINT NOT NULL REFERENCES paciente(persona_id),
  dependiente_id BIGINT NOT NULL UNIQUE REFERENCES paciente(persona_id),   -- un solo responsable
  parentesco     VARCHAR(15) NOT NULL CHECK (parentesco IN ('HIJO','PADRE','MADRE','CONYUGE','TUTELADO')),
  fecha_vinculo  TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (responsable_id, dependiente_id),
  CHECK (responsable_id <> dependiente_id)
);

-- =====================================================================
-- 3. ESPECIALIDADES Y MÉDICOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS especialidad (
  id             SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre         VARCHAR(60)   NOT NULL UNIQUE,
  descripcion    VARCHAR(250),
  costo_consulta NUMERIC(10,2) NOT NULL CHECK (costo_consulta > 0),
  edad_minima    SMALLINT      NOT NULL DEFAULT 0 CHECK (edad_minima >= 0),
  edad_maxima    SMALLINT      CHECK (edad_maxima IS NULL OR edad_maxima >= edad_minima), 
  sexo_permitido CHAR(1)       NOT NULL DEFAULT 'A' CHECK (sexo_permitido IN ('A','F','M')), 
  estado         BOOLEAN       NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS medico (                 
  persona_id  BIGINT PRIMARY KEY REFERENCES persona(id),
  matricula   VARCHAR(20) NOT NULL UNIQUE,
  estado      VARCHAR(10) NOT NULL DEFAULT 'ACTIVO' CHECK (estado IN ('ACTIVO','INACTIVO')),
  fecha_baja  DATE,
  motivo_baja VARCHAR(200),
  CHECK ((estado = 'INACTIVO') = (fecha_baja IS NOT NULL AND motivo_baja IS NOT NULL))
);

DO $$ BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_ficha_medico') THEN
    ALTER TABLE ficha_clinica ADD CONSTRAINT fk_ficha_medico FOREIGN KEY (actualizado_por) REFERENCES medico(persona_id);
  END IF;
END $$;

CREATE TABLE IF NOT EXISTS medico_especialidad (       
  medico_id       BIGINT   NOT NULL REFERENCES medico(persona_id),
  especialidad_id SMALLINT NOT NULL REFERENCES especialidad(id),
  PRIMARY KEY (medico_id, especialidad_id)
);

-- =====================================================================
-- 4. AGENDAS Y HORARIOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS jornada (
  id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  medico_id           BIGINT   NOT NULL REFERENCES medico(persona_id),
  dia_semana          SMALLINT NOT NULL CHECK (dia_semana BETWEEN 1 AND 7), 
  hora_inicio         TIME     NOT NULL,
  hora_fin            TIME     NOT NULL,
  duracion_bloque_min SMALLINT NOT NULL DEFAULT 30 CHECK (duracion_bloque_min BETWEEN 20 AND 45),
  vigente             BOOLEAN  NOT NULL DEFAULT TRUE,
  rango               rango_hora GENERATED ALWAYS AS (rango_hora(hora_inicio, hora_fin)) STORED,
  CHECK (hora_fin > hora_inicio),
  CONSTRAINT ex_jornada_superpuesta EXCLUDE USING gist
    (medico_id WITH =, dia_semana WITH =, rango WITH &&) WHERE (vigente)
);

CREATE TABLE IF NOT EXISTS bloqueo_agenda (
  id                BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  medico_id         BIGINT      NOT NULL REFERENCES medico(persona_id),
  fecha_hora_inicio TIMESTAMP   NOT NULL,
  fecha_hora_fin    TIMESTAMP   NOT NULL,
  tipo              VARCHAR(12) NOT NULL CHECK (tipo IN ('VACACIONES','BAJA_MEDICA','PERMISO','CONGRESO')),
  motivo            VARCHAR(200) NOT NULL,
  registrado_por    BIGINT      NOT NULL REFERENCES usuario(id),
  CHECK (fecha_hora_fin > fecha_hora_inicio),
  CONSTRAINT ex_bloqueo_superpuesto EXCLUDE USING gist
    (medico_id WITH =, tsrange(fecha_hora_inicio, fecha_hora_fin) WITH &&)
);

-- =====================================================================
-- 5. PROGRAMACIÓN DE CITAS
-- =====================================================================
CREATE TABLE IF NOT EXISTS cita (
  id                 BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  paciente_id        BIGINT      NOT NULL REFERENCES paciente(persona_id),
  medico_id          BIGINT      NOT NULL,
  especialidad_id    SMALLINT    NOT NULL,
  fecha              DATE        NOT NULL,
  hora_inicio        TIME        NOT NULL,
  hora_fin           TIME        NOT NULL,
  periodo            TSRANGE GENERATED ALWAYS AS (tsrange(fecha + hora_inicio, fecha + hora_fin)) STORED,
  estado             VARCHAR(12) NOT NULL DEFAULT 'PROGRAMADA'
                     CHECK (estado IN ('PROGRAMADA','CONFIRMADA','ATENDIDA','CANCELADA','INASISTENCIA')),
  motivo_consulta    VARCHAR(250),
  origen             VARCHAR(10) NOT NULL CHECK (origen IN ('RECEPCION','PORTAL','MEDICO')),
  excepcion_filtro   BOOLEAN     NOT NULL DEFAULT FALSE,     
  motivo_excepcion   VARCHAR(250),
  motivo_cancelacion VARCHAR(250),
  registrado_por     BIGINT      NOT NULL REFERENCES usuario(id),
  fecha_registro     TIMESTAMPTZ NOT NULL DEFAULT now(),
  -- el médico debe ejercer la especialidad de la cita
  FOREIGN KEY (medico_id, especialidad_id) REFERENCES medico_especialidad (medico_id, especialidad_id),
  CHECK (hora_fin > hora_inicio),
  CHECK (NOT excepcion_filtro OR (origen = 'MEDICO' AND motivo_excepcion IS NOT NULL)),
  CHECK (estado <> 'CANCELADA' OR motivo_cancelacion IS NOT NULL),
  -- RN05: un médico no atiende dos citas a la vez y un paciente no tiene dos citas cruzadas
  CONSTRAINT ex_cita_medico EXCLUDE USING gist
    (medico_id WITH =, periodo WITH &&) WHERE (estado IN ('PROGRAMADA','CONFIRMADA')),
  CONSTRAINT ex_cita_paciente EXCLUDE USING gist
    (paciente_id WITH =, periodo WITH &&) WHERE (estado IN ('PROGRAMADA','CONFIRMADA'))
);
CREATE INDEX IF NOT EXISTS ix_cita_medico_fecha ON cita (medico_id, fecha);
CREATE INDEX IF NOT EXISTS ix_cita_paciente     ON cita (paciente_id);

CREATE TABLE IF NOT EXISTS historial_estado_cita (    
  id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  cita_id         BIGINT      NOT NULL REFERENCES cita(id) ON DELETE CASCADE,
  estado_anterior VARCHAR(12),
  estado_nuevo    VARCHAR(12) NOT NULL,
  usuario_id      BIGINT REFERENCES usuario(id),
  fecha_hora      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS atencion (                
  cita_id            BIGINT PRIMARY KEY REFERENCES cita(id) ON DELETE CASCADE,
  peso_kg            NUMERIC(5,2) CHECK (peso_kg > 0 AND peso_kg < 400),
  presion_sistolica  SMALLINT CHECK (presion_sistolica BETWEEN 50 AND 260),
  presion_diastolica SMALLINT CHECK (presion_diastolica BETWEEN 30 AND 160),
  observaciones      TEXT,
  fecha_hora         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- =====================================================================
-- 6. HISTORIAL CLÍNICO Y LABORATORIO
-- =====================================================================
CREATE TABLE IF NOT EXISTS tipo_examen (
  id          SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre      VARCHAR(80)   NOT NULL UNIQUE,
  descripcion VARCHAR(250),
  costo       NUMERIC(10,2) NOT NULL CHECK (costo > 0),
  estado      BOOLEAN       NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS solicitud_examen (
  id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  paciente_id     BIGINT        NOT NULL REFERENCES paciente(persona_id),
  tipo_examen_id  SMALLINT      NOT NULL REFERENCES tipo_examen(id),
  medico_id       BIGINT REFERENCES medico(persona_id),   
  cita_id         BIGINT REFERENCES cita(id),              
  costo           NUMERIC(10,2) NOT NULL CHECK (costo > 0), 
  estado          VARCHAR(22)   NOT NULL DEFAULT 'SOLICITADO'
                  CHECK (estado IN ('SOLICITADO','RESULTADO_DISPONIBLE','ANULADO')),
  registrado_por  BIGINT        NOT NULL REFERENCES usuario(id),
  fecha_solicitud TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS resultado_laboratorio (   
  id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  solicitud_examen_id BIGINT       NOT NULL UNIQUE REFERENCES solicitud_examen(id) ON DELETE CASCADE,
  nombre_archivo      VARCHAR(150) NOT NULL,
  ruta_archivo        VARCHAR(255) NOT NULL,       
  tipo_mime           VARCHAR(20)  NOT NULL CHECK (tipo_mime IN ('application/pdf','image/jpeg','image/png')),
  tamano_bytes        INTEGER      NOT NULL CHECK (tamano_bytes > 0 AND tamano_bytes <= 5242880),  -- 5 MB
  subido_por          BIGINT       NOT NULL REFERENCES usuario(id),
  fecha_subida        TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- =====================================================================
-- 7. PAGOS Y COMPROBANTES (pasarela Stripe)
-- =====================================================================
CREATE TABLE IF NOT EXISTS pago (
  id                       BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  cita_id                  BIGINT UNIQUE REFERENCES cita(id),
  solicitud_examen_id      BIGINT UNIQUE REFERENCES solicitud_examen(id),
  monto                    NUMERIC(10,2) NOT NULL CHECK (monto > 0),
  moneda                   CHAR(3)       NOT NULL DEFAULT 'BOB',
  metodo                   VARCHAR(10)   CHECK (metodo IN ('STRIPE','SEGURO')),
  estado                   VARCHAR(10)   NOT NULL DEFAULT 'PENDIENTE' CHECK (estado IN ('PENDIENTE','PAGADO','CANCELADO')),
  stripe_session_id        VARCHAR(255) UNIQUE,
  stripe_payment_intent_id VARCHAR(255) UNIQUE,
  fecha_pago               TIMESTAMPTZ,
  registrado_por           BIGINT REFERENCES usuario(id),

  CHECK (num_nonnulls(cita_id, solicitud_examen_id) = 1),
  CHECK (estado <> 'PAGADO' OR (metodo IS NOT NULL AND fecha_pago IS NOT NULL)),
  CHECK (metodo IS DISTINCT FROM 'STRIPE' OR estado <> 'PAGADO' OR stripe_payment_intent_id IS NOT NULL)
);

CREATE TABLE IF NOT EXISTS comprobante (              
  id            BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  pago_id       BIGINT       NOT NULL UNIQUE REFERENCES pago(id),
  numero        VARCHAR(16)  NOT NULL UNIQUE
                DEFAULT 'REC-' || to_char(now(), 'YYYY') || '-' || lpad(nextval('seq_comprobante')::text, 5, '0'),
  fecha_emision TIMESTAMPTZ  NOT NULL DEFAULT now(),
  ruta_pdf      VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS evento_pasarela (          
  id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  stripe_event_id VARCHAR(255) NOT NULL UNIQUE,     
  tipo            VARCHAR(80)  NOT NULL,            
  pago_id         BIGINT REFERENCES pago(id),
  firma_valida    BOOLEAN      NOT NULL,
  procesado       BOOLEAN      NOT NULL DEFAULT FALSE,
  payload         JSONB        NOT NULL,
  recibido_en     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- =====================================================================
-- 8. NOTIFICACIONES Y PARÁMETROS
-- =====================================================================
CREATE TABLE IF NOT EXISTS notificacion (
  id                  BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  usuario_id          BIGINT      NOT NULL REFERENCES usuario(id),
  tipo                VARCHAR(22) NOT NULL CHECK (tipo IN ('CITA_REGISTRADA','CITA_CONFIRMADA','CITA_REPROGRAMADA',
                        'CITA_CANCELADA','INASISTENCIA','PAGO_APROBADO','RESULTADO_DISPONIBLE','RECORDATORIO')),
  titulo              VARCHAR(80) NOT NULL,
  mensaje             VARCHAR(300) NOT NULL,
  cita_id             BIGINT REFERENCES cita(id),
  pago_id             BIGINT REFERENCES pago(id),
  solicitud_examen_id BIGINT REFERENCES solicitud_examen(id),
  leida               BOOLEAN     NOT NULL DEFAULT FALSE,
  fecha_creacion      TIMESTAMPTZ NOT NULL DEFAULT now(),
  fecha_lectura       TIMESTAMPTZ,
  CHECK (num_nonnulls(cita_id, pago_id, solicitud_examen_id) <= 1)
);
CREATE INDEX IF NOT EXISTS ix_notificacion_no_leidas ON notificacion (usuario_id) WHERE NOT leida;
CREATE UNIQUE INDEX IF NOT EXISTS ux_recordatorio_unico ON notificacion (usuario_id, cita_id) WHERE tipo = 'RECORDATORIO';

CREATE TABLE IF NOT EXISTS parametro_sistema (
  clave       VARCHAR(40) PRIMARY KEY,
  valor       VARCHAR(100) NOT NULL,
  descripcion VARCHAR(200)
);

-- =====================================================================
-- 9. FUNCIONES Y TRIGGERS (reglas de negocio en la base de datos)
-- =====================================================================

-- Bitácora inalterable (RN14): rechaza UPDATE y DELETE
CREATE OR REPLACE FUNCTION fn_bitacora_inmutable() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  RAISE EXCEPTION 'La bitácora es de solo inserción';
END $$;
DROP TRIGGER IF EXISTS tg_bitacora_inmutable ON bitacora;
CREATE TRIGGER tg_bitacora_inmutable BEFORE UPDATE OR DELETE ON bitacora
  FOR EACH ROW EXECUTE FUNCTION fn_bitacora_inmutable();

-- Registro común en bitácora; la aplicación envía la IP de la solicitud
CREATE OR REPLACE FUNCTION fn_registrar_bitacora(p_usuario BIGINT, p_accion VARCHAR, p_descripcion TEXT,
  p_ip INET DEFAULT NULL, p_tabla VARCHAR DEFAULT NULL, p_registro BIGINT DEFAULT NULL)
RETURNS VOID LANGUAGE sql AS $$
  INSERT INTO bitacora (usuario_id, accion, descripcion, ip, tabla, registro_id)
  VALUES (p_usuario, p_accion, p_descripcion, p_ip, p_tabla, p_registro);
$$;

-- Validación de la cita: médico, especialidad, jornada, bloques, bloqueos y filtro de edad y sexo 
CREATE OR REPLACE FUNCTION fn_validar_cita() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
  v_persona persona%ROWTYPE;
  v_esp     especialidad%ROWTYPE;
  v_edad    INT;
  v_ok      BOOLEAN;
BEGIN
  IF NEW.estado NOT IN ('PROGRAMADA','CONFIRMADA') THEN RETURN NEW; END IF;

  IF (TG_OP = 'INSERT' OR NEW.fecha <> OLD.fecha OR NEW.hora_inicio <> OLD.hora_inicio)
     AND (NEW.fecha + NEW.hora_inicio) <= localtimestamp THEN
    RAISE EXCEPTION 'No se puede agendar una cita en una fecha u hora pasada';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM medico WHERE persona_id = NEW.medico_id AND estado = 'ACTIVO') THEN
    RAISE EXCEPTION 'El médico no está activo';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM paciente WHERE persona_id = NEW.paciente_id AND estado = 'ACTIVO') THEN
    RAISE EXCEPTION 'El paciente no está activo';
  END IF;
  SELECT * INTO v_esp FROM especialidad WHERE id = NEW.especialidad_id;
  IF NOT v_esp.estado THEN RAISE EXCEPTION 'La especialidad no está activa'; END IF;


  SELECT EXISTS (
    SELECT 1 FROM jornada j
    WHERE j.medico_id = NEW.medico_id AND j.vigente
      AND j.dia_semana = EXTRACT(ISODOW FROM NEW.fecha)
      AND NEW.hora_inicio >= j.hora_inicio AND NEW.hora_fin <= j.hora_fin
      AND (NEW.hora_fin - NEW.hora_inicio) = make_interval(mins => j.duracion_bloque_min)
      AND (EXTRACT(EPOCH FROM (NEW.hora_inicio - j.hora_inicio))::INT / 60) % j.duracion_bloque_min = 0
  ) INTO v_ok;
  IF NOT v_ok THEN RAISE EXCEPTION 'El horario no corresponde a un cupo de la jornada del médico'; END IF;

  
  IF EXISTS (SELECT 1 FROM bloqueo_agenda b WHERE b.medico_id = NEW.medico_id
             AND tsrange(b.fecha_hora_inicio, b.fecha_hora_fin) && tsrange(NEW.fecha + NEW.hora_inicio, NEW.fecha + NEW.hora_fin)) THEN

    RAISE EXCEPTION 'El médico tiene un bloqueo de agenda en ese horario';
  END IF;

  -- filtro de edad y sexo por especialidad 
  IF NOT NEW.excepcion_filtro THEN
    SELECT * INTO v_persona FROM persona WHERE id = NEW.paciente_id;
    v_edad := EXTRACT(YEAR FROM age(NEW.fecha, v_persona.fecha_nacimiento));
    IF v_edad < v_esp.edad_minima OR (v_esp.edad_maxima IS NOT NULL AND v_edad > v_esp.edad_maxima)
       OR (v_esp.sexo_permitido <> 'A' AND v_esp.sexo_permitido <> v_persona.sexo) THEN
      RAISE EXCEPTION 'La especialidad seleccionada no corresponde a la edad o sexo del paciente';
    END IF;
  END IF;
  RETURN NEW;
END $$;
DROP TRIGGER IF EXISTS tg_validar_cita ON cita;
CREATE TRIGGER tg_validar_cita BEFORE INSERT OR UPDATE OF fecha, hora_inicio, hora_fin, medico_id, especialidad_id, paciente_id
  ON cita FOR EACH ROW EXECUTE FUNCTION fn_validar_cita();

-- Transiciones de estado permitidas (RN06)
CREATE OR REPLACE FUNCTION fn_transicion_estado_cita() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.estado = OLD.estado THEN RETURN NEW; END IF;
  IF NOT (
       (OLD.estado = 'PROGRAMADA' AND NEW.estado IN ('CONFIRMADA','CANCELADA','INASISTENCIA'))
    OR (OLD.estado = 'CONFIRMADA' AND NEW.estado IN ('ATENDIDA','CANCELADA','INASISTENCIA'))
  ) THEN
    RAISE EXCEPTION 'Transición de estado no permitida: % -> %', OLD.estado, NEW.estado;
  END IF;
  IF NEW.estado IN ('ATENDIDA','INASISTENCIA') AND (NEW.fecha + NEW.hora_inicio) > localtimestamp THEN
    RAISE EXCEPTION 'No se puede registrar % antes de la fecha y hora de la cita', NEW.estado;
  END IF;
  RETURN NEW;
END $$;
DROP TRIGGER IF EXISTS tg_transicion_estado_cita ON cita;
CREATE TRIGGER tg_transicion_estado_cita BEFORE UPDATE OF estado ON cita
  FOR EACH ROW EXECUTE FUNCTION fn_transicion_estado_cita();


CREATE OR REPLACE FUNCTION fn_historial_estado_cita() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF TG_OP = 'INSERT' OR NEW.estado <> OLD.estado THEN
    INSERT INTO historial_estado_cita (cita_id, estado_anterior, estado_nuevo, usuario_id)
    VALUES (NEW.id, CASE WHEN TG_OP = 'UPDATE' THEN OLD.estado END, NEW.estado,
            NULLIF(current_setting('app.usuario_id', TRUE), '')::BIGINT);
  END IF;
  RETURN NEW;
END $$;
DROP TRIGGER IF EXISTS tg_historial_estado_cita ON cita;
CREATE TRIGGER tg_historial_estado_cita AFTER INSERT OR UPDATE OF estado ON cita
  FOR EACH ROW EXECUTE FUNCTION fn_historial_estado_cita();

-- Pago aprobado: confirma la cita y emite el comprobante 
CREATE OR REPLACE FUNCTION fn_pago_aprobado() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.estado = 'PAGADO' AND OLD.estado <> 'PAGADO' THEN
    IF NEW.cita_id IS NOT NULL THEN
      UPDATE cita SET estado = 'CONFIRMADA' WHERE id = NEW.cita_id AND estado = 'PROGRAMADA';
    END IF;
    INSERT INTO comprobante (pago_id) VALUES (NEW.id) ON CONFLICT (pago_id) DO NOTHING;
  END IF;
  RETURN NEW;
END $$;
DROP TRIGGER IF EXISTS tg_pago_aprobado ON pago;
CREATE TRIGGER tg_pago_aprobado AFTER UPDATE OF estado ON pago
  FOR EACH ROW EXECUTE FUNCTION fn_pago_aprobado();

-- CU13: cupos libres de un médico en una fecha (jornada - citas activas - bloqueos - horas pasadas)
CREATE OR REPLACE FUNCTION fn_cupos_disponibles(p_medico BIGINT, p_fecha DATE)
RETURNS TABLE (hora_inicio TIME, hora_fin TIME) LANGUAGE sql STABLE AS $$
  SELECT c.ini::time, (c.ini + make_interval(mins => j.duracion_bloque_min))::time
  FROM jornada j
  CROSS JOIN LATERAL generate_series(p_fecha + j.hora_inicio,
                                     p_fecha + j.hora_fin - make_interval(mins => j.duracion_bloque_min),
                                     make_interval(mins => j.duracion_bloque_min)) AS c(ini)
  WHERE j.medico_id = p_medico AND j.vigente
    AND j.dia_semana = EXTRACT(ISODOW FROM p_fecha)
    AND c.ini > localtimestamp
    AND NOT EXISTS (SELECT 1 FROM cita ci WHERE ci.medico_id = p_medico AND ci.estado IN ('PROGRAMADA','CONFIRMADA')
                    AND ci.periodo && tsrange(c.ini, c.ini + make_interval(mins => j.duracion_bloque_min)))
    AND NOT EXISTS (SELECT 1 FROM bloqueo_agenda b WHERE b.medico_id = p_medico
                    AND tsrange(b.fecha_hora_inicio, b.fecha_hora_fin) && tsrange(c.ini, c.ini + make_interval(mins => j.duracion_bloque_min)))
  ORDER BY 1;
$$;

-- CU06: especialidades permitidas para un paciente en una fecha
CREATE OR REPLACE FUNCTION fn_especialidades_permitidas(p_paciente BIGINT, p_fecha DATE)
RETURNS SETOF especialidad LANGUAGE sql STABLE AS $$
  SELECT e.* FROM especialidad e, persona p
  WHERE p.id = p_paciente AND e.estado
    AND EXTRACT(YEAR FROM age(p_fecha, p.fecha_nacimiento)) >= e.edad_minima
    AND (e.edad_maxima IS NULL OR EXTRACT(YEAR FROM age(p_fecha, p.fecha_nacimiento)) <= e.edad_maxima)
    AND e.sexo_permitido IN ('A', p.sexo)
  ORDER BY e.nombre;
$$;

-- =====================================================================
-- 10. VISTAS DE REPORTES (los reportes no se almacenan: se calculan)
-- =====================================================================
CREATE OR REPLACE VIEW v_cita_detalle AS
SELECT c.id AS cita_id, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.origen,
       pa.nro_expediente, pp.nombres || ' ' || pp.apellidos AS paciente,
       pm.nombres || ' ' || pm.apellidos AS medico, e.nombre AS especialidad,
       pg.estado AS estado_pago, pg.monto
FROM cita c
JOIN paciente pa ON pa.persona_id = c.paciente_id
JOIN persona  pp ON pp.id = c.paciente_id
JOIN persona  pm ON pm.id = c.medico_id
JOIN especialidad e ON e.id = c.especialidad_id
LEFT JOIN pago pg ON pg.cita_id = c.id;

CREATE OR REPLACE VIEW v_rep_demanda_especialidad AS
SELECT e.nombre AS especialidad, date_trunc('month', c.fecha)::date AS mes,
       count(*) AS total_citas,
       count(*) FILTER (WHERE c.estado = 'ATENDIDA')     AS atendidas,
       count(*) FILTER (WHERE c.estado = 'CANCELADA')    AS canceladas,
       count(*) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias
FROM cita c JOIN especialidad e ON e.id = c.especialidad_id
GROUP BY e.nombre, date_trunc('month', c.fecha);

CREATE OR REPLACE VIEW v_rep_ausentismo_medico AS
SELECT pm.nombres || ' ' || pm.apellidos AS medico, date_trunc('month', c.fecha)::date AS mes,
       count(*) FILTER (WHERE c.estado IN ('ATENDIDA','INASISTENCIA')) AS citas_vencidas,
       count(*) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias,
       round(100.0 * count(*) FILTER (WHERE c.estado = 'INASISTENCIA')
             / NULLIF(count(*) FILTER (WHERE c.estado IN ('ATENDIDA','INASISTENCIA')), 0), 2) AS porcentaje_ausentismo
FROM cita c JOIN persona pm ON pm.id = c.medico_id
GROUP BY pm.nombres, pm.apellidos, date_trunc('month', c.fecha);

CREATE OR REPLACE VIEW v_rep_ingresos AS
SELECT date_trunc('month', p.fecha_pago)::date AS mes, p.metodo,
       CASE WHEN p.cita_id IS NOT NULL THEN 'CONSULTA' ELSE 'LABORATORIO' END AS servicio,
       count(*) AS cantidad, sum(p.monto) AS total
FROM pago p WHERE p.estado = 'PAGADO'
GROUP BY 1, 2, 3;

CREATE OR REPLACE VIEW v_paciente_asistencia AS         
SELECT c.paciente_id,
       count(*) FILTER (WHERE c.estado = 'ATENDIDA')     AS atendidas,
       count(*) FILTER (WHERE c.estado = 'INASISTENCIA') AS inasistencias
FROM cita c GROUP BY c.paciente_id;

-- =====================================================================
-- 11. DATOS INICIALES
-- =====================================================================
INSERT INTO rol (nombre, descripcion) VALUES
  ('ADMINISTRADOR', 'Configuración, seguridad y supervisión'),
  ('RECEPCIONISTA', 'Atención de pacientes, citas y cobros'),
  ('MEDICO',        'Atención de sus citas y ficha clínica'),
  ('PACIENTE',      'Autogestión de citas, pagos y resultados')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO permiso (modulo, accion)
SELECT m, a FROM unnest(ARRAY['SEGURIDAD','PACIENTES','ESPECIALIDADES_MEDICOS','AGENDAS','CITAS',
                              'PAGOS','HISTORIAL_LABORATORIO','NOTIFICACIONES','REPORTES']) AS m,
                 unnest(ARRAY['C','R','M','A']) AS a
ON CONFLICT (modulo, accion) DO NOTHING;


INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM rol r JOIN permiso p ON
  (r.nombre = 'ADMINISTRADOR' AND (p.modulo IN ('SEGURIDAD','PACIENTES','ESPECIALIDADES_MEDICOS','AGENDAS')
                                   OR (p.modulo = 'CITAS' AND p.accion IN ('C','M','A'))
                                   OR (p.modulo = 'PAGOS' AND p.accion IN ('C','A'))
                                   OR (p.modulo IN ('HISTORIAL_LABORATORIO','REPORTES') AND p.accion = 'C')
                                   OR (p.modulo = 'NOTIFICACIONES' AND p.accion IN ('C','M'))))
  OR (r.nombre = 'RECEPCIONISTA' AND ((p.modulo = 'PACIENTES' AND p.accion IN ('C','R','M'))
                                   OR (p.modulo IN ('ESPECIALIDADES_MEDICOS','AGENDAS','NOTIFICACIONES') AND p.accion = 'C')
                                   OR p.modulo = 'CITAS'
                                   OR (p.modulo IN ('PAGOS','HISTORIAL_LABORATORIO') AND p.accion IN ('C','R'))))
  OR (r.nombre = 'MEDICO'        AND ((p.modulo IN ('PACIENTES','ESPECIALIDADES_MEDICOS','AGENDAS','NOTIFICACIONES') AND p.accion = 'C')
                                   OR (p.modulo IN ('CITAS','HISTORIAL_LABORATORIO') AND p.accion IN ('C','R','M'))))
  OR (r.nombre = 'PACIENTE'      AND ((p.modulo = 'PACIENTES' AND p.accion IN ('C','R','M'))
                                   OR (p.modulo IN ('ESPECIALIDADES_MEDICOS','HISTORIAL_LABORATORIO','NOTIFICACIONES') AND p.accion = 'C')
                                   OR (p.modulo = 'CITAS' AND p.accion IN ('C','R','A'))
                                   OR (p.modulo = 'PAGOS' AND p.accion IN ('C','R'))))
ON CONFLICT DO NOTHING;

-- Especialidades con sus reglas de edad y sexo (1.4.4). Costos de ejemplo: los define el administrador.
INSERT INTO especialidad (nombre, costo_consulta, edad_minima, edad_maxima, sexo_permitido) VALUES
  ('Medicina General',                100.00, 0,  NULL, 'A'),
  ('Odontología',                     100.00, 0,  NULL, 'A'),
  ('Pediatría y Neonatología',        100.00, 0,  11,   'A'),
  ('Ginecología y Obstetricia',       100.00, 12, NULL, 'F'),
  ('Cardiología',                     100.00, 18, NULL, 'A'),
  ('Gastroenterología',               100.00, 18, NULL, 'A'),
  ('Neumología',                      100.00, 18, NULL, 'A'),
  ('Neurología',                      100.00, 18, NULL, 'A'),
  ('Endocrinología',                  100.00, 18, NULL, 'A'),
  ('Nefrología',                      100.00, 18, NULL, 'A'),
  ('Oncología',                       100.00, 18, NULL, 'A'),
  ('Cirugía General y Laparoscópica', 100.00, 18, NULL, 'A'),
  ('Urología',                        100.00, 18, NULL, 'A')
ON CONFLICT (nombre) DO NOTHING;

INSERT INTO parametro_sistema (clave, valor, descripcion) VALUES
  ('HORAS_RECORDATORIO',      '24', 'Anticipación del recordatorio de citas (RN13)'),
  ('DURACION_BLOQUE_DEFECTO', '30', 'Duración de consulta por defecto en minutos (RN04)'),
  ('TAMANO_MAX_ARCHIVO_MB',   '5',  'Tamaño máximo de resultados de laboratorio (RN12)')
ON CONFLICT (clave) DO NOTHING;

-- Administrador inicial
INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo)
VALUES ('0000000', 'Administrador', 'General', DATE '1990-01-01', 'M')
ON CONFLICT (ci) DO NOTHING;
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, debe_cambiar_contrasena)
SELECT p.id, r.id, 'admin@vidasalud.com', crypt('Admin*2026', gen_salt('bf', 10)), TRUE
FROM persona p, rol r
WHERE p.ci = '0000000' AND r.nombre = 'ADMINISTRADOR'
  AND NOT EXISTS (SELECT 1 FROM usuario u WHERE lower(u.correo) = 'admin@vidasalud.com');
