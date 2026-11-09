-- =====================================================================================
--  Clínica Vida Salud — 02_poblacion.sql : DATOS DE EJEMPLO (pruebas y defensa)
--
--  Ejecutar DESPUÉS de 01_estructura.sql, sobre esa base recién creada.
--  Las fechas se calculan desde el día en que se ejecuta: siempre hay historial
--  (últimos 90 días) y agenda futura (próximos 21 días).
--
--  Contenido: 14 especialidades, 3 recepcionistas, 16 médicos con jornadas y bloqueos,
--  160 personas en el portal (110 pacientes adultos, 40 menores, 10 responsables que no son
--  pacientes), cuentas web, vínculos familiares, ~800 citas con pagos y comprobantes,
--  exámenes de laboratorio, notificaciones, conversaciones del asistente y bitácora.
--
--  Contraseña de TODAS las cuentas de ejemplo: Clinica123*
--  Correos de pacientes: dominio example.com (reservado para pruebas: nunca se entrega un
--  correo real). Para recibir avisos en vivo, cambie 2 o 3 correos por alias de su Gmail
--  (por ejemplo sucorreo+paciente@gmail.com).
-- =====================================================================================
BEGIN;
SELECT setseed(0.2026);

-- ======================= 1. ESPECIALIDADES ===========================================
INSERT INTO especialidad (nombre, descripcion, costo_consulta, edad_minima, edad_maxima, sexo_permitido) VALUES
 ('Medicina General',       'Atención primaria, control y derivación',              80.00,  0, NULL, 'A'),
 ('Pediatría',              'Atención de niños y adolescentes',                    100.00,  0,   14, 'A'),
 ('Ginecología',            'Salud de la mujer',                                   150.00, 12, NULL, 'F'),
 ('Odontología',            'Salud bucal',                                         100.00,  3, NULL, 'A'),
 ('Cardiología',            'Corazón y sistema circulatorio',                      180.00, 15, NULL, 'A'),
 ('Dermatología',           'Piel, cabello y uñas',                                150.00,  0, NULL, 'A'),
 ('Traumatología',          'Huesos, articulaciones y lesiones',                   160.00,  0, NULL, 'A'),
 ('Oftalmología',           'Salud visual',                                        140.00,  0, NULL, 'A'),
 ('Otorrinolaringología',   'Oído, nariz y garganta',                              140.00,  0, NULL, 'A'),
 ('Neurología',             'Sistema nervioso',                                    200.00, 15, NULL, 'A'),
 ('Urología',               'Sistema urinario y salud del varón',                  170.00, 15, NULL, 'M'),
 ('Geriatría',              'Atención integral del adulto mayor',                  130.00, 60, NULL, 'A'),
 ('Nutrición',              'Alimentación y control de peso',                       90.00,  5, NULL, 'A'),
 ('Psicología',             'Salud mental y orientación',                          120.00,  6, NULL, 'A');

-- ======================= 2. PERSONAL: RECEPCIÓN Y MÉDICOS ============================
INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion) VALUES
 ('2000001', 'María Fernanda', 'López Choque',     '1992-02-20', 'F', '70020010', 'Av. Banzer 4to anillo, Santa Cruz'),
 ('2000002', 'Jorge Luis',     'Suárez Vaca',      '1988-07-11', 'M', '70020020', 'Barrio Equipetrol, Santa Cruz'),
 ('2000003', 'Paola Andrea',   'Gutiérrez Rivero', '1996-11-03', 'F', '70020030', 'Av. Alemana 3er anillo, Santa Cruz'),
 ('3000001', 'Carlos Alberto', 'Rojas Vargas',     '1980-04-12', 'M', '70030010', 'Calle Libertad 245, Santa Cruz'),
 ('3000002', 'Ana Lucía',      'Flores Quispe',    '1985-09-30', 'F', '70030020', 'Av. Cristo Redentor 1550, Santa Cruz'),
 ('3000003', 'Verónica',       'Salazar Rojas',    '1980-12-17', 'F', '70030030', 'Barrio Hamacas, Santa Cruz'),
 ('3000004', 'Ernesto',        'Calle Vargas',     '1973-12-31', 'M', '70030040', 'Av. Busch 3er anillo, Santa Cruz'),
 ('3000005', 'Carolina',       'Mendoza Arce',     '1987-12-25', 'F', '70030050', 'Calle Warnes 120, Santa Cruz'),
 ('3000006', 'Héctor',         'Torrez Nina',      '1984-12-10', 'M', '70030060', 'Av. Piraí 2do anillo, Santa Cruz'),
 ('3000007', 'Lorena',         'Peñaranda Soliz',  '1979-08-31', 'F', '70030070', 'Barrio Las Palmas, Santa Cruz'),
 ('3000008', 'Raúl',           'Gutiérrez Camacho','1971-02-11', 'M', '70030080', 'Av. Roca y Coronado, Santa Cruz'),
 ('3000009', 'Paula',          'Limachi Ortiz',    '1990-06-14', 'F', '70030090', 'Calle Sucre 88, Santa Cruz'),
 ('3000010', 'Gonzalo',        'Aliaga Zeballos',  '1982-04-11', 'M', '70030100', 'Av. Santos Dumont 5to anillo, Santa Cruz'),
 ('3000011', 'Mariela',        'Rivero Condori',   '1987-04-29', 'F', '70030110', 'Barrio Sirari, Santa Cruz'),
 ('3000012', 'Walter',         'Apaza Villarroel', '1976-05-24', 'M', '70030120', 'Av. Grigotá 3er anillo, Santa Cruz'),
 ('3000013', 'Daniela',        'Justiniano Paz',   '1989-03-08', 'F', '70030130', 'Calle Ayacucho 410, Santa Cruz'),
 ('3000014', 'Rodrigo',        'Méndez Saucedo',   '1983-10-19', 'M', '70030140', 'Av. Mutualista 4to anillo, Santa Cruz'),
 ('3000015', 'Silvia',         'Antelo Rocha',     '1978-01-27', 'F', '70030150', 'Barrio Urbarí, Santa Cruz'),
 ('3000016', 'Fernando',       'Vaca Díez Roca',   '1975-06-02', 'M', '70030160', 'Av. Irala 2do anillo, Santa Cruz');

-- Cuentas institucionales del personal (asignadas por el administrador)
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, fecha_creacion)
SELECT p.id, r.id, v.correo, '$2y$10$RLV.AlqeqC0sbobwgG0q6.Twz.EoPQI..Knx7C0MIldzfxNA2ZRfS', now() - interval '120 days'
  FROM (VALUES
   ('2000001','RECEPCIONISTA','maria.lopez@clinicavidasalud.com'),
   ('2000002','RECEPCIONISTA','jorge.suarez@clinicavidasalud.com'),
   ('2000003','RECEPCIONISTA','paola.gutierrez@clinicavidasalud.com'),
   ('3000001','MEDICO','carlos.rojas@clinicavidasalud.com'),
   ('3000002','MEDICO','ana.flores@clinicavidasalud.com'),
   ('3000003','MEDICO','veronica.salazar@clinicavidasalud.com'),
   ('3000004','MEDICO','ernesto.calle@clinicavidasalud.com'),
   ('3000005','MEDICO','carolina.mendoza@clinicavidasalud.com'),
   ('3000006','MEDICO','hector.torrez@clinicavidasalud.com'),
   ('3000007','MEDICO','lorena.penaranda@clinicavidasalud.com'),
   ('3000008','MEDICO','raul.gutierrez@clinicavidasalud.com'),
   ('3000009','MEDICO','paula.limachi@clinicavidasalud.com'),
   ('3000010','MEDICO','gonzalo.aliaga@clinicavidasalud.com'),
   ('3000011','MEDICO','mariela.rivero@clinicavidasalud.com'),
   ('3000012','MEDICO','walter.apaza@clinicavidasalud.com'),
   ('3000013','MEDICO','daniela.justiniano@clinicavidasalud.com'),
   ('3000014','MEDICO','rodrigo.mendez@clinicavidasalud.com'),
   ('3000015','MEDICO','silvia.antelo@clinicavidasalud.com'),
   ('3000016','MEDICO','fernando.vacadiez@clinicavidasalud.com')
  ) AS v(ci, rol, correo)
  JOIN persona p ON p.ci = v.ci
  JOIN rol r ON r.nombre = v.rol;

INSERT INTO medico (persona_id, matricula, fecha_alta)
SELECT p.id, 'MP-' || (1000 + right(p.ci, 2)::int), now() - interval '120 days'
  FROM persona p WHERE p.ci LIKE '3%';

INSERT INTO medico_especialidad (medico_id, especialidad_id)
SELECT p.id, e.id
  FROM (VALUES
   ('3000001','Medicina General'), ('3000001','Pediatría'),
   ('3000002','Ginecología'),
   ('3000003','Medicina General'),
   ('3000004','Medicina General'), ('3000004','Geriatría'),
   ('3000005','Pediatría'),
   ('3000006','Odontología'),
   ('3000007','Dermatología'),
   ('3000008','Cardiología'),
   ('3000009','Ginecología'),
   ('3000010','Traumatología'),
   ('3000011','Oftalmología'), ('3000011','Otorrinolaringología'),
   ('3000012','Geriatría'), ('3000012','Medicina General'),
   ('3000013','Nutrición'),
   ('3000014','Urología'),
   ('3000015','Neurología'),
   ('3000016','Psicología')
  ) AS v(ci, especialidad)
  JOIN persona p ON p.ci = v.ci
  JOIN especialidad e ON e.nombre = v.especialidad;

-- Jornadas: turno mañana (08:00-12:00) o tarde (14:00-18:00) de lunes a viernes; algunos atienden el sábado
INSERT INTO jornada (medico_id, dia_semana, hora_inicio, hora_fin, duracion_bloque_min, fecha_creacion)
SELECT p.id, d.dia, v.inicio::time, v.fin::time, v.bloque, now() - interval '120 days'
  FROM (VALUES
   ('3000001','08:00','12:00',20), ('3000002','14:00','18:00',30), ('3000003','14:00','18:00',20),
   ('3000004','08:00','12:00',20), ('3000005','14:00','18:00',30), ('3000006','08:00','12:00',30),
   ('3000007','14:00','18:00',30), ('3000008','08:00','12:00',30), ('3000009','08:00','12:00',30),
   ('3000010','14:00','18:00',30), ('3000011','08:00','12:00',30), ('3000012','14:00','18:00',30),
   ('3000013','08:00','12:00',40), ('3000014','14:00','18:00',30), ('3000015','08:00','12:00',40),
   ('3000016','14:00','18:00',60)
  ) AS v(ci, inicio, fin, bloque)
  JOIN persona p ON p.ci = v.ci
 CROSS JOIN generate_series(1, 5) AS d(dia);

INSERT INTO jornada (medico_id, dia_semana, hora_inicio, hora_fin, duracion_bloque_min, fecha_creacion)
SELECT p.id, 6, '08:00', '12:00', 20, now() - interval '120 days'
  FROM persona p WHERE p.ci IN ('3000001', '3000003', '3000006');

-- Bloqueos de agenda (CU12): ausencias pasadas y futuras
INSERT INTO bloqueo_agenda (medico_id, fecha_inicio, fecha_fin, tipo_bloqueo, motivo)
SELECT p.id, (current_date + v.desde)::timestamp + v.h_ini::time, (current_date + v.hasta)::timestamp + v.h_fin::time, v.tipo, v.motivo
  FROM (VALUES
   ('3000007', -40, -33, '00:00', '23:59', 'VACACIONES', 'Vacaciones anuales'),
   ('3000010', -15, -15, '14:00', '18:00', 'PERMISO',    'Permiso personal'),
   ('3000002',   5,   5, '00:00', '23:59', 'PERMISO',    'Capacitación'),
   ('3000008',  10,  12, '00:00', '23:59', 'VACACIONES', 'Congreso de cardiología'),
   ('3000003',   3,   3, '16:00', '18:00', 'REUNION',    'Reunión de comité médico')
  ) AS v(ci, desde, hasta, h_ini, h_fin, tipo, motivo)
  JOIN persona p ON p.ci = v.ci;

-- ======================= 3. PACIENTES, CUENTAS Y PERSONAS A CARGO ====================
--  n = 1..45    adultos con expediente y cuenta web (1..15 se registraron en línea)
--  n = 46..110  adultos con expediente, registrados por recepción, SIN cuenta web
--  n = 111..120 responsables con cuenta web que NO son pacientes (solo sacan fichas)
--  n = 121..160 menores de edad con expediente, sin cuenta, a cargo de un adulto
CREATE TEMP TABLE tmp_gente (n INT PRIMARY KEY, ci TEXT, nombres TEXT, apellidos TEXT, sexo CHAR(1), nacimiento DATE) ON COMMIT DROP;
INSERT INTO tmp_gente
SELECT n,
       '5' || lpad(n::text, 6, '0'),
       CASE WHEN n % 2 = 0
            THEN (ARRAY['Lucía','Sofía','Valentina','Camila','Daniela','Gabriela','Andrea','Mariana','Paola','Fernanda',
                        'Carla','Natalia','Rosa','Elena','Patricia','Claudia','Silvia','Jimena','Mónica','Teresa'])[1 + (n * 7 + n / 20) % 20]
            ELSE (ARRAY['Juan','Carlos','Luis','José','Miguel','Diego','Andrés','Jorge','Pedro','Ricardo',
                        'Fernando','Rodrigo','Mateo','Sebastián','Gabriel','Iván','Marcelo','Sergio','Óscar','Álvaro'])[1 + (n * 7 + n / 20) % 20]
       END,
       (ARRAY['Pérez','Rojas','Gutiérrez','Vaca','Suárez','Mamani','Quispe','Flores','Justiniano','Rivero',
              'Antelo','Montaño','Céspedes','Saucedo','Zeballos','Méndez','Paz','Aguilera','Roca','Soliz'])[1 + (n * 3) % 20]
       || ' ' ||
       (ARRAY['Vargas','Choque','Arce','Salazar','Ortiz','Camacho','Rocha','Condori','Limachi','Moreno',
              'Peña','Coimbra','Vaca','Durán','Chávez','Cuéllar','Melgar','Ribera','Terrazas','Añez'])[1 + (n * 11 + 3 * (n / 20)) % 20],
       CASE WHEN n % 2 = 0 THEN 'F' ELSE 'M' END,
       CASE WHEN n BETWEEN 111 AND 120 THEN DATE '1976-01-01' + ((n * 397) % 5000)   -- responsables de 1976 a 1989
            WHEN n <= 120 THEN DATE '1948-01-01' + ((n * 2711) % 20500)             -- adultos de 1948 a 2004
            ELSE current_date - ((n - 120) * 155 + 200)                              -- menores de 0 a 17 años
       END
  FROM generate_series(1, 160) AS n;

-- Las personas con correo demo fijo (fácil de recordar para la defensa)
UPDATE tmp_gente SET nombres = 'Juan Carlos', apellidos = 'Pérez Vargas',   sexo = 'M', nacimiento = '1990-03-15' WHERE n = 1;
UPDATE tmp_gente SET nombres = 'Lucía',       apellidos = 'Gómez Torrez',   sexo = 'F', nacimiento = '1995-01-20' WHERE n = 2;
UPDATE tmp_gente SET nombres = 'Pedro',       apellidos = 'Quispe Huanca',  sexo = 'M', nacimiento = '1975-11-02' WHERE n = 46;
UPDATE tmp_gente SET nombres = 'Roberto',     apellidos = 'Añez Suárez',    sexo = 'M', nacimiento = '1984-06-09' WHERE n = 111;
UPDATE tmp_gente SET nombres = 'Sofía',       apellidos = 'Pérez Gómez',    sexo = 'F', nacimiento = current_date - 2900 WHERE n = 121;
UPDATE tmp_gente SET nombres = 'Mateo',       apellidos = 'Añez Rivero',    sexo = 'M', nacimiento = current_date - 1600 WHERE n = 141;
UPDATE tmp_gente SET nombres = 'Valentina',   apellidos = 'Añez Rivero',    sexo = 'F', nacimiento = current_date - 4100 WHERE n = 142;

-- Menores: responsable según el grupo. 121..140 de un paciente con cuenta, 141..150 de un responsable
-- que no es paciente, 151..160 de un paciente sin cuenta (lo vinculó recepción).
CREATE TEMP TABLE tmp_menor (n INT PRIMARY KEY, responsable INT, sufijo INT) ON COMMIT DROP;
INSERT INTO tmp_menor
SELECT n,
       CASE WHEN n = 121 THEN 1
            WHEN n BETWEEN 122 AND 140 THEN 2 + (n - 122) % 40
            WHEN n IN (141, 142) THEN 111
            WHEN n BETWEEN 143 AND 150 THEN 112 + (n - 143)
            ELSE 46 + (n - 151) * 3 END,
       0
  FROM generate_series(121, 160) AS n;
-- Cada menor queda a cargo de un adulto con edad de ser su padre o madre (de 20 a 45 años mayor)
WITH elegibles AS (
    SELECT t.n AS menor, a.n AS adulto,
           row_number() OVER (PARTITION BY t.n ORDER BY abs((m.nacimiento - a.nacimiento) - 30 * 365), a.n) AS orden
      FROM tmp_menor t JOIN tmp_gente m ON m.n = t.n
      JOIN tmp_gente a ON a.n BETWEEN CASE WHEN t.n <= 140 THEN 2 ELSE 46 END AND CASE WHEN t.n <= 140 THEN 45 ELSE 110 END
     WHERE (t.n BETWEEN 122 AND 140 OR t.n >= 151)
       AND m.nacimiento - a.nacimiento BETWEEN 20 * 365 AND 45 * 365
       AND (a.n + t.n) % 3 <> 0)                            -- reparte a los menores entre varios adultos
UPDATE tmp_menor t SET responsable = e.adulto FROM elegibles e WHERE e.menor = t.n AND e.orden = 1;

UPDATE tmp_menor t SET sufijo = x.orden
  FROM (SELECT n, row_number() OVER (PARTITION BY responsable ORDER BY n) AS orden FROM tmp_menor) x
 WHERE x.n = t.n;
-- El menor lleva el primer apellido de su responsable
UPDATE tmp_gente g SET apellidos = split_part(r.apellidos, ' ', 1) || ' ' || split_part(g.apellidos, ' ', 2)
  FROM tmp_menor t JOIN tmp_gente r ON r.n = t.responsable
 WHERE g.n = t.n AND g.n NOT IN (121, 141, 142);

-- Si el menor salió con el mismo nombre que su responsable, se le da otro
UPDATE tmp_gente g SET nombres = CASE WHEN g.sexo = 'F'
           THEN (ARRAY['Abril','Renata','Antonella','Luciana','Emilia','Isabella'])[1 + g.n % 6]
           ELSE (ARRAY['Thiago','Benjamín','Joaquín','Santiago','Emiliano','Lucas'])[1 + g.n % 6] END
  FROM tmp_menor t JOIN tmp_gente r ON r.n = t.responsable
 WHERE g.n = t.n AND g.nombres = r.nombres;

-- Los menores de 10 años no tienen CI propio: usan el CI del responsable + sufijo, como en el portal
INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion, fecha_registro)
SELECT g.ci, g.nombres, g.apellidos, g.nacimiento, g.sexo,
       CASE WHEN g.n <= 120 THEN '7' || lpad(((g.n * 7919) % 9999999)::text, 7, '0') END,
       CASE WHEN g.n <= 120 THEN (ARRAY['Barrio Equipetrol','Av. Banzer 5to anillo','Barrio Las Palmas','Av. Paraguá 4to anillo',
                                       'Plan 3000','Villa 1ro de Mayo','Barrio Hamacas','Av. Virgen de Cotoca 6to anillo',
                                       'Barrio Sirari','Av. Doble Vía La Guardia km 6'])[1 + g.n % 10] || ', Santa Cruz' END,
       GREATEST(now() - make_interval(days => 100 + (g.n * 13) % 300), g.nacimiento + interval '7 days')
  FROM tmp_gente g;

-- Expedientes (todos menos los responsables que no se atienden). nro_expediente por orden de registro.
INSERT INTO paciente (persona_id, contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono,
                      aseguradora_id, nro_poliza, estado, fecha_registro)
SELECT p.id,
       CASE WHEN g.n BETWEEN 1 AND 15 THEN NULL       -- se registraron en línea: recepción completa estos datos
            ELSE (ARRAY['Rosa','Mario','Elena','Hugo','Carmen','Julio','Isabel','Raúl'])[1 + g.n % 8] || ' ' || split_part(g.apellidos, ' ', 1) END,
       CASE WHEN g.n BETWEEN 1 AND 15 THEN NULL
            ELSE (ARRAY['Madre','Padre','Esposa','Esposo','Hermana','Hermano','Hija','Hijo'])[1 + g.n % 8] END,
       CASE WHEN g.n BETWEEN 1 AND 15 THEN NULL ELSE '6' || lpad(((g.n * 104729) % 9999999)::text, 7, '0') END,
       CASE WHEN g.n % 4 = 0 THEN (SELECT id FROM aseguradora ORDER BY id OFFSET (g.n / 4) % 6 LIMIT 1) END,
       CASE WHEN g.n % 4 = 0 THEN 'POL-' || lpad((g.n * 37)::text, 5, '0') END,
       CASE WHEN g.n IN (97, 103, 109) THEN 'INACTIVO' ELSE 'ACTIVO' END,
       p.fecha_registro
  FROM tmp_gente g JOIN persona p ON p.ci = g.ci
 WHERE g.n NOT BETWEEN 111 AND 120
 ORDER BY p.fecha_registro;

-- Ficha clínica (composición 1..1): datos variados
INSERT INTO ficha_clinica (paciente_id, tipo_sangre, alergias, enfermedades_cronicas, antecedentes_familiares, antecedentes_quirurgicos)
SELECT pa.persona_id,
       (ARRAY['O+','O+','O+','A+','A+','B+','AB+','O-','A-','No especificado'])[1 + (pa.persona_id % 10)::int],
       (ARRAY['Ninguna conocida','Penicilina','Polen','Mariscos','No especificado','Ninguna conocida','Ibuprofeno'])[1 + (pa.persona_id % 7)::int],
       (ARRAY['Ninguna','Hipertensión arterial','Diabetes tipo 2','Asma','No especificado','Ninguna','Hipotiroidismo'])[1 + ((pa.persona_id / 3) % 7)::int],
       (ARRAY['Diabetes (madre)','Hipertensión (padre)','Ninguno conocido','Cardiopatía (abuelo)','No especificado'])[1 + ((pa.persona_id / 2) % 5)::int],
       (ARRAY['Ninguno','Apendicectomía','Cesárea','Colecistectomía','No especificado','Ninguno'])[1 + ((pa.persona_id / 5) % 6)::int]
  FROM paciente pa;

-- Cuentas web: adultos 1..45 (pacientes) y 111..120 (responsables que no son pacientes)
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash, avisos_whatsapp, ultimo_acceso, fecha_creacion)
SELECT p.id, (SELECT id FROM rol WHERE nombre = 'PACIENTE'),
       CASE g.n WHEN 1 THEN 'juan.perez@example.com' WHEN 2 THEN 'lucia.gomez@example.com' WHEN 111 THEN 'roberto.anez@example.com'
            ELSE lower(translate(split_part(g.nombres, ' ', 1) || '.' || split_part(g.apellidos, ' ', 1), 'áéíóúñÁÉÍÓÚÑ', 'aeiounAEIOUN'))
                 || g.n || '@example.com' END,
       '$2y$10$RLV.AlqeqC0sbobwgG0q6.Twz.EoPQI..Knx7C0MIldzfxNA2ZRfS',
       g.n % 5 = 0,
       now() - make_interval(days => g.n % 20, hours => g.n % 9),
       p.fecha_registro + interval '1 hour'
  FROM tmp_gente g JOIN persona p ON p.ci = g.ci
 WHERE g.n <= 45 OR g.n BETWEEN 111 AND 120;

-- Responsable legal de cada menor y su vínculo
UPDATE persona p SET tutor_id = r.id
  FROM tmp_menor t JOIN tmp_gente g ON g.n = t.n JOIN tmp_gente rg ON rg.n = t.responsable JOIN persona r ON r.ci = rg.ci
 WHERE p.ci = g.ci;
UPDATE persona p SET ci = r.ci || '-' || t.sufijo
  FROM tmp_menor t JOIN tmp_gente g ON g.n = t.n JOIN tmp_gente rg ON rg.n = t.responsable JOIN persona r ON r.ci = rg.ci
 WHERE p.ci = g.ci AND g.nacimiento > current_date - interval '10 years';

INSERT INTO vinculo_familiar (paciente_id, familiar_id, parentesco, fecha_vinculo)
SELECT p.tutor_id, p.id, 'HIJO', p.fecha_registro
  FROM persona p WHERE p.tutor_id IS NOT NULL;

-- Adultos a cargo de una cuenta («Para otra persona»): madres, padres y cónyuges
INSERT INTO vinculo_familiar (paciente_id, familiar_id, parentesco, fecha_vinculo)
SELECT r.id, f.id, v.parentesco, now() - interval '60 days'
  FROM (VALUES (1, 48, 'MADRE'), (2, 47, 'PADRE'), (3, 50, 'CONYUGE'), (5, 54, 'MADRE'), (8, 55, 'HERMANO'),
               (111, 58, 'CONYUGE'), (112, 62, 'MADRE'), (20, 64, 'OTRO')) AS v(nr, nf, parentesco)
  JOIN tmp_gente rg ON rg.n = v.nr JOIN persona r ON r.ci = rg.ci
  JOIN tmp_gente fg ON fg.n = v.nf JOIN persona f ON f.ci = fg.ci;

-- ======================= 4. CITAS, PAGOS Y COMPROBANTES ==============================
DO $$
DECLARE
    v_recep    BIGINT[] := ARRAY(SELECT u.id FROM usuario u JOIN persona p ON p.id = u.persona_id WHERE p.ci LIKE '2%' ORDER BY p.ci);
    r          RECORD;
    v_cita     BIGINT;
    v_estado   TEXT;
    v_fecha    DATE;
    v_ini      TIME;
    v_paciente BIGINT;
    v_esp      SMALLINT;
    v_origen   TEXT;
    v_por      BIGINT;
    v_pasadas  INT := 0;
    v_futuras  INT := 0;
    v_intentos INT := 0;
BEGIN
    ALTER TABLE cita DISABLE TRIGGER tg_validar_cita;     -- el historial (fechas pasadas) no pasa por la validación de fecha

    -- ---------- Historial: ~640 citas en los últimos 90 días ----------
    WHILE v_pasadas < 640 AND v_intentos < 20000 LOOP
        v_intentos := v_intentos + 1;
        v_fecha := current_date - floor(random() * 90)::int;          -- incluye hoy (horas que ya pasaron)
        SELECT j.medico_id, j.hora_inicio, j.hora_fin, j.duracion_bloque_min AS dur INTO r
          FROM jornada j WHERE j.vigente AND j.dia_semana = EXTRACT(ISODOW FROM v_fecha) ORDER BY random() LIMIT 1;
        CONTINUE WHEN r.medico_id IS NULL;
        v_ini := r.hora_inicio + make_interval(mins => r.dur * floor(random() * (EXTRACT(EPOCH FROM r.hora_fin - r.hora_inicio) / 60 / r.dur))::int);
        CONTINUE WHEN v_fecha + v_ini + make_interval(mins => r.dur) > localtimestamp
                   OR EXISTS (SELECT 1 FROM cita c WHERE c.medico_id = r.medico_id AND c.fecha = v_fecha AND c.hora_inicio = v_ini)
                   OR EXISTS (SELECT 1 FROM bloqueo_agenda b WHERE b.medico_id = r.medico_id
                                 AND tsrange(b.fecha_inicio, b.fecha_fin) && tsrange(v_fecha + v_ini, v_fecha + v_ini + make_interval(mins => r.dur)));
        SELECT pa.persona_id, f.id INTO v_paciente, v_esp
          FROM paciente pa
          JOIN fn_especialidades_permitidas(pa.persona_id, v_fecha) f ON TRUE
          JOIN medico_especialidad me ON me.medico_id = r.medico_id AND me.especialidad_id = f.id
         WHERE pa.estado = 'ACTIVO' AND pa.fecha_registro::date <= v_fecha
           AND NOT EXISTS (SELECT 1 FROM cita c WHERE c.paciente_id = pa.persona_id AND c.fecha = v_fecha)
         ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_paciente IS NULL;
        v_origen := CASE WHEN random() < 0.30 AND EXISTS (SELECT 1 FROM usuario WHERE persona_id = v_paciente) THEN 'PORTAL' ELSE 'RECEPCION' END;
        v_por := CASE WHEN v_origen = 'PORTAL' THEN (SELECT id FROM usuario WHERE persona_id = v_paciente)
                      ELSE v_recep[1 + floor(random() * 3)::int] END;
        PERFORM set_config('app.usuario_id', v_por::text, TRUE);
        BEGIN
            INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta, origen, registrado_por, fecha_registro)
            VALUES (v_paciente, r.medico_id, v_esp, v_fecha, v_ini, v_ini + make_interval(mins => r.dur),
                    (ARRAY['Control general','Dolor persistente','Revisión de resultados','Primera consulta','Seguimiento',
                           'Chequeo anual','Malestar general','Control de tratamiento'])[1 + floor(random() * 8)::int],
                    v_origen, v_por, (v_fecha - (1 + floor(random() * 10))::int)::timestamp + time '09:00')
            RETURNING id INTO v_cita;
        EXCEPTION WHEN exclusion_violation THEN
            CONTINUE;
        END;
        INSERT INTO pago (cita_id, monto, estado, registrado_por, fecha_registro)
        SELECT v_cita, e.costo_consulta, 'PENDIENTE', v_por, c.fecha_registro
          FROM cita c JOIN especialidad e ON e.id = c.especialidad_id WHERE c.id = v_cita;

        IF v_fecha >= current_date - 2 AND random() < (CASE WHEN v_fecha = current_date THEN 0.6 ELSE 0.10 END) THEN
            v_estado := 'CONFIRMADA';                      -- pendientes de marcar (CU15/CU17)
        ELSE
            v_estado := CASE WHEN random() < 0.70 THEN 'ATENDIDA' WHEN random() < 0.55 THEN 'CANCELADA' ELSE 'INASISTENCIA' END;
        END IF;

        IF v_estado IN ('ATENDIDA', 'CONFIRMADA') OR (v_estado = 'CANCELADA' AND random() < 0.4) OR (v_estado = 'INASISTENCIA' AND random() < 0.2) THEN
            UPDATE cita SET estado = 'CONFIRMADA' WHERE id = v_cita;
            -- cobro: seguro con copago si tiene póliza; si no, un método al azar
            UPDATE pago pg
               SET estado = 'PAGADO',
                   metodo = CASE WHEN pa.aseguradora_id IS NOT NULL AND random() < 0.6 THEN 'SEGURO'
                                 ELSE (ARRAY['EFECTIVO','EFECTIVO','QR','QR','TARJETA','TRANSFERENCIA','STRIPE'])[1 + floor(random() * 7)::int] END,
                   fecha_pago = c.fecha + c.hora_inicio - interval '20 minutes'
              FROM cita c JOIN paciente pa ON pa.persona_id = c.paciente_id
             WHERE pg.cita_id = v_cita AND c.id = v_cita;
            UPDATE pago SET referencia_externa = 'cs_test_' || md5(id::text)::varchar(24), fecha_pago = fecha_registro + interval '5 minutes'
             WHERE cita_id = v_cita AND metodo = 'STRIPE';
            UPDATE pago SET copago = round(monto * 0.2, 2), copago_metodo = 'EFECTIVO', copago_fecha = fecha_pago
             WHERE cita_id = v_cita AND metodo = 'SEGURO';
            INSERT INTO comprobante (pago_id, fecha_emision) SELECT id, fecha_pago FROM pago WHERE cita_id = v_cita;
        END IF;
        IF v_estado <> 'CONFIRMADA' THEN
            UPDATE cita SET estado = v_estado,
                   motivo_cancelacion = CASE WHEN v_estado = 'CANCELADA'
                        THEN (ARRAY['El paciente no puede asistir','Viaje imprevisto','Se sintió mejor','Cambio de horario laboral',
                                    'Cancelada por el médico'])[1 + floor(random() * 5)::int] END
             WHERE id = v_cita;
            -- lo que se pagó antes de cancelar o faltar se pagó al reservar (en línea o en caja)
            UPDATE pago SET fecha_pago = fecha_registro + interval '10 minutes'
             WHERE cita_id = v_cita AND estado = 'PAGADO' AND v_estado IN ('CANCELADA', 'INASISTENCIA');
            UPDATE comprobante cp SET fecha_emision = pg.fecha_pago FROM pago pg
             WHERE pg.id = cp.pago_id AND pg.cita_id = v_cita AND v_estado IN ('CANCELADA', 'INASISTENCIA');
            UPDATE pago SET estado = CASE WHEN v_estado = 'CANCELADA' AND estado = 'PAGADO' THEN 'REEMBOLSADO'
                                          WHEN estado = 'PENDIENTE' THEN 'ANULADO' ELSE estado END
             WHERE cita_id = v_cita AND v_estado IN ('CANCELADA', 'INASISTENCIA');
        END IF;
        v_pasadas := v_pasadas + 1;
    END LOOP;

    ALTER TABLE cita ENABLE TRIGGER tg_validar_cita;      -- la agenda futura SÍ se valida con el trigger

    -- ---------- Agenda: ~170 citas en los próximos 21 días (cupos reales de fn_cupos_disponibles) ----------
    v_intentos := 0;
    WHILE v_futuras < 170 AND v_intentos < 8000 LOOP
        v_intentos := v_intentos + 1;
        v_fecha := current_date + floor(random() * 22)::int;          -- desde hoy (horas que faltan) hasta 21 días
        SELECT j.medico_id INTO r FROM jornada j
         WHERE j.vigente AND j.dia_semana = EXTRACT(ISODOW FROM v_fecha) ORDER BY random() LIMIT 1;
        CONTINUE WHEN r.medico_id IS NULL;
        SELECT c.hora_inicio INTO v_ini FROM fn_cupos_disponibles(r.medico_id, v_fecha) c ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_ini IS NULL;
        SELECT pa.persona_id, f.id INTO v_paciente, v_esp
          FROM paciente pa
          JOIN fn_especialidades_permitidas(pa.persona_id, v_fecha) f ON TRUE
          JOIN medico_especialidad me ON me.medico_id = r.medico_id AND me.especialidad_id = f.id
         WHERE pa.estado = 'ACTIVO'
           AND NOT EXISTS (SELECT 1 FROM cita c WHERE c.paciente_id = pa.persona_id AND c.fecha = v_fecha)
         ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_paciente IS NULL;
        -- quién la sacó: el paciente, su responsable (cuenta) o recepción
        SELECT u.id INTO v_por FROM usuario u
         WHERE u.persona_id = v_paciente
            OR u.persona_id = (SELECT tutor_id FROM persona WHERE id = v_paciente)
            OR u.persona_id IN (SELECT paciente_id FROM vinculo_familiar WHERE familiar_id = v_paciente)
         ORDER BY (u.persona_id = v_paciente) DESC LIMIT 1;
        IF v_por IS NOT NULL AND random() < 0.6 THEN
            v_origen := CASE WHEN random() < 0.15 THEN 'CHATBOT' ELSE 'PORTAL' END;
        ELSE
            v_origen := 'RECEPCION';
            v_por := v_recep[1 + floor(random() * 3)::int];
        END IF;
        PERFORM set_config('app.usuario_id', v_por::text, TRUE);
        BEGIN
            INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta, origen, registrado_por, fecha_registro)
            SELECT v_paciente, r.medico_id, v_esp, v_fecha, c.hora_inicio, c.hora_fin,
                   (ARRAY['Control general','Dolor persistente','Revisión de resultados','Primera consulta','Seguimiento','Chequeo anual'])[1 + floor(random() * 6)::int],
                   v_origen, v_por, now() - make_interval(days => floor(random() * 6)::int, hours => floor(random() * 10)::int)
              FROM fn_cupos_disponibles(r.medico_id, v_fecha) c WHERE c.hora_inicio = v_ini
            RETURNING id INTO v_cita;
        EXCEPTION WHEN exclusion_violation THEN
            CONTINUE;
        END;
        INSERT INTO pago (cita_id, monto, estado, registrado_por, fecha_registro)
        SELECT v_cita, e.costo_consulta, 'PENDIENTE', v_por, c.fecha_registro
          FROM cita c JOIN especialidad e ON e.id = c.especialidad_id WHERE c.id = v_cita;
        IF random() < 0.45 THEN                            -- pagada = confirmada
            UPDATE cita SET estado = 'CONFIRMADA' WHERE id = v_cita;
            UPDATE pago SET estado = 'PAGADO',
                   metodo = CASE WHEN v_origen IN ('PORTAL', 'CHATBOT') THEN 'STRIPE' ELSE (ARRAY['EFECTIVO','QR','TRANSFERENCIA'])[1 + floor(random() * 3)::int] END,
                   fecha_pago = fecha_registro + interval '10 minutes'
             WHERE cita_id = v_cita;
            UPDATE pago SET referencia_externa = 'cs_test_' || md5(id::text)::varchar(24) WHERE cita_id = v_cita AND metodo = 'STRIPE';
            INSERT INTO comprobante (pago_id, fecha_emision) SELECT id, fecha_pago FROM pago WHERE cita_id = v_cita;
        END IF;
        IF random() < 0.08 THEN
            UPDATE cita SET veces_reprogramada = 1 WHERE id = v_cita;   -- ya se movió una vez (CU16)
        END IF;
        v_futuras := v_futuras + 1;
    END LOOP;
    RAISE NOTICE 'Citas creadas: % de historial y % en agenda', v_pasadas, v_futuras;
END $$;

-- Trazabilidad con fechas reales: historial de estados y cola de eventos
UPDATE historial_estado_cita h SET fecha_hora = CASE
         WHEN h.estado_anterior IS NULL THEN c.fecha_registro
         WHEN h.estado_nuevo = 'CONFIRMADA' THEN COALESCE((SELECT fecha_pago FROM pago WHERE cita_id = c.id), c.fecha_registro + interval '1 hour')
         WHEN h.estado_nuevo = 'CANCELADA' THEN GREATEST(LEAST(c.fecha + c.hora_inicio - interval '1 day', now()),
                                                         COALESCE((SELECT fecha_pago FROM pago WHERE cita_id = c.id), c.fecha_registro) + interval '1 hour')
         ELSE c.fecha + c.hora_fin END
  FROM cita c WHERE c.id = h.cita_id;
UPDATE evento_paciente SET procesado = TRUE, fecha_evento = LEAST(fecha_evento, now() - interval '1 hour');

-- ======================= 5. LABORATORIO ==============================================
INSERT INTO solicitud_examen (cita_id, tipo_examen_id, fecha_solicitud)
SELECT c.id, (SELECT id FROM tipo_examen ORDER BY id OFFSET (c.id % 10) LIMIT 1), c.fecha + c.hora_fin
  FROM cita c
 WHERE c.estado = 'ATENDIDA' AND c.id % 4 = 0
   AND NOT EXISTS (SELECT 1 FROM especialidad e WHERE e.id = c.especialidad_id AND e.nombre IN ('Odontología', 'Psicología'));
-- La prueba de embarazo solo a mujeres de 12 años o más
UPDATE solicitud_examen s SET tipo_examen_id = (SELECT id FROM tipo_examen WHERE nombre = 'Hemograma completo')
  FROM cita c JOIN persona p ON p.id = c.paciente_id
 WHERE c.id = s.cita_id AND s.tipo_examen_id = (SELECT id FROM tipo_examen WHERE nombre = 'Prueba de embarazo')
   AND (p.sexo = 'M' OR age(p.fecha_nacimiento) < interval '12 years');
UPDATE solicitud_examen s SET costo = t.costo FROM tipo_examen t WHERE t.id = s.tipo_examen_id;

-- Resultados: los más antiguos ya entregados, los recientes listos; algunos aún en proceso o cancelados
INSERT INTO resultado_laboratorio (solicitud_examen_id, resultado, fecha_resultado)
SELECT s.id,
       (ARRAY['Valores dentro de los rangos de referencia.',
              'Leve elevación respecto al rango de referencia; se sugiere control en 3 meses.',
              'Sin hallazgos patológicos.',
              'Resultado compatible con el diagnóstico presuntivo; ver indicaciones del médico.'])[1 + (s.id % 4)::int],
       s.fecha_solicitud + interval '2 days'
  FROM solicitud_examen s
 WHERE s.fecha_solicitud < now() - interval '3 days' AND s.id % 9 <> 0;
UPDATE solicitud_examen SET estado = 'ENTREGADO'
 WHERE estado = 'RESULTADO_LISTO' AND fecha_solicitud < now() - interval '20 days';
UPDATE solicitud_examen SET estado = 'EN_PROCESO' WHERE estado = 'SOLICITADO' AND id % 2 = 0;
UPDATE solicitud_examen SET estado = 'CANCELADO'  WHERE estado = 'SOLICITADO' AND id % 3 = 0 AND fecha_solicitud < now() - interval '15 days';
-- El trigger tg_resultado_listo avisó con la fecha de hoy; se reemplazan por avisos con la fecha real del resultado
DELETE FROM notificacion WHERE asunto = 'Resultado de laboratorio disponible';
INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje, leida, fecha_creacion, fecha_envio)
SELECT u.id, c.id, 'SISTEMA', 'CORREO', 'Resultado de laboratorio disponible',
       'El resultado de ' || t.nombre || ' de ' || pp.nombres || ' ya está disponible en el sistema.',
       r.fecha_resultado < now() - interval '5 days', r.fecha_resultado, r.fecha_resultado + interval '1 minute'
  FROM resultado_laboratorio r
  JOIN solicitud_examen s ON s.id = r.solicitud_examen_id
  JOIN tipo_examen t ON t.id = s.tipo_examen_id
  JOIN cita c ON c.id = s.cita_id
  JOIN persona pp ON pp.id = c.paciente_id
  JOIN usuario u ON u.persona_id = COALESCE((SELECT us.persona_id FROM usuario us WHERE us.persona_id = pp.id), pp.tutor_id);
UPDATE evento_paciente SET procesado = TRUE;

-- ======================= 6. NOTIFICACIONES ===========================================
-- Destinatario: la cuenta del paciente o, si no tiene, la de su responsable (tutor o vínculo).
CREATE TEMP TABLE tmp_destino ON COMMIT DROP AS
SELECT c.id AS cita_id, c.codigo, c.fecha, c.hora_inicio, c.estado, c.fecha_registro, c.motivo_cancelacion,
       c.veces_reprogramada, pp.nombres || ' ' || pp.apellidos AS paciente, pm.nombres || ' ' || pm.apellidos AS medico,
       e.nombre AS especialidad,
       COALESCE(u.id, ut.id, (SELECT us.id FROM vinculo_familiar vf JOIN usuario us ON us.persona_id = vf.paciente_id
                              WHERE vf.familiar_id = c.paciente_id ORDER BY vf.id LIMIT 1)) AS usuario_id
  FROM cita c
  JOIN persona pp ON pp.id = c.paciente_id
  JOIN persona pm ON pm.id = c.medico_id
  JOIN especialidad e ON e.id = c.especialidad_id
  LEFT JOIN usuario u  ON u.persona_id = c.paciente_id
  LEFT JOIN usuario ut ON ut.persona_id = pp.tutor_id
 WHERE c.fecha >= current_date - 30;
DELETE FROM tmp_destino WHERE usuario_id IS NULL;

INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje, leida, fecha_creacion, fecha_envio)
SELECT usuario_id, cita_id, 'CONFIRMACION', 'CORREO', 'Cita registrada',
       'Registramos la cita ' || codigo || ' de ' || paciente || ' del ' || to_char(fecha, 'DD/MM/YYYY') || ' a las '
       || to_char(hora_inicio, 'HH24:MI') || ' con ' || medico || ' (' || especialidad || ').',
       fecha < current_date, fecha_registro, fecha_registro + interval '2 minutes'
  FROM tmp_destino;
INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje, leida, fecha_creacion, fecha_envio)
SELECT usuario_id, cita_id, 'RECORDATORIO', 'CORREO', 'Recordatorio de su cita médica',
       'Le recordamos la cita de ' || paciente || ' el ' || to_char(fecha, 'DD/MM/YYYY') || ' a las ' || to_char(hora_inicio, 'HH24:MI')
       || ' con ' || medico || ' (' || especialidad || '). Código: ' || codigo || '.',
       fecha < current_date, (fecha - 1)::timestamp + time '08:00', (fecha - 1)::timestamp + time '08:01'
  FROM tmp_destino WHERE fecha <= current_date + 1 AND estado IN ('ATENDIDA', 'INASISTENCIA', 'CONFIRMADA', 'PROGRAMADA');
INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje, leida, fecha_creacion, fecha_envio)
SELECT tmp_destino.usuario_id, tmp_destino.cita_id, 'CANCELACION', 'CORREO', 'Cita cancelada',
       'Se canceló la cita ' || codigo || ' de ' || paciente || ' del ' || to_char(fecha, 'DD/MM/YYYY') || '. Motivo: ' || motivo_cancelacion || '.',
       TRUE, h.fecha_hora, h.fecha_hora + interval '2 minutes'
  FROM tmp_destino JOIN historial_estado_cita h ON h.cita_id = tmp_destino.cita_id AND h.estado_nuevo = 'CANCELADA'
 WHERE tmp_destino.estado = 'CANCELADA';
INSERT INTO notificacion (usuario_id, cita_id, tipo, canal, asunto, mensaje, leida, fecha_creacion, fecha_envio)
SELECT usuario_id, cita_id, 'SISTEMA', 'CORREO', 'Cita reprogramada',
       'La cita ' || codigo || ' de ' || paciente || ' quedó para el ' || to_char(fecha, 'DD/MM/YYYY') || ' a las ' || to_char(hora_inicio, 'HH24:MI') || '.',
       FALSE, fecha_registro + interval '1 day', fecha_registro + interval '1 day'
  FROM tmp_destino WHERE veces_reprogramada > 0;

-- ======================= 7. ASISTENTE VIRTUAL ========================================
DO $$
DECLARE
    v_conv UUID;
    v_pac  BIGINT;
    v_cuando TIMESTAMPTZ;
    k INT;
    f RECORD;
BEGIN
    FOR k IN 1..36 LOOP
        v_cuando := now() - make_interval(days => k * 2, hours => k % 7);
        v_pac := CASE WHEN k % 3 = 0 THEN (SELECT u.persona_id FROM usuario u JOIN paciente pa ON pa.persona_id = u.persona_id
                                            ORDER BY u.id OFFSET k LIMIT 1) END;     -- 1 de cada 3, un paciente con sesión
        INSERT INTO conversacion (paciente_id, ip, fecha_inicio)
        VALUES (v_pac, ('181.188.' || (10 + k % 50) || '.' || (20 + k))::inet, v_cuando) RETURNING id INTO v_conv;
        SELECT * INTO f FROM pregunta_frecuente ORDER BY id OFFSET (k % 11) LIMIT 1;
        INSERT INTO mensaje_chat (conversacion_id, emisor, texto, intencion, fecha_hora) VALUES
         (v_conv, 'PACIENTE', f.pregunta, NULL, v_cuando),
         (v_conv, 'ASISTENTE', f.respuesta, 'FAQ', v_cuando + interval '2 seconds');
        IF k % 4 = 0 THEN
            INSERT INTO mensaje_chat (conversacion_id, emisor, texto, intencion, fecha_hora) VALUES
             (v_conv, 'PACIENTE', '¿Atienden con seguro de la Caja Petrolera?', NULL, v_cuando + interval '1 minute'),
             (v_conv, 'ASISTENTE', 'No tengo una respuesta exacta. Consulte en recepción o reformule su pregunta.', 'SIN_RESPUESTA', v_cuando + interval '62 seconds');
        END IF;
        IF k > 2 THEN
            UPDATE conversacion SET estado = 'CERRADA', fecha_fin = v_cuando + interval '5 minutes' WHERE id = v_conv;
        END IF;
    END LOOP;
END $$;

-- ======================= 8. BITÁCORA Y RECUPERACIÓN DE CONTRASEÑA ====================
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT (SELECT id FROM usuario WHERE correo = 'admin@clinicavidasalud.com'), 'CREAR_USUARIO',
       'Cuenta creada para ' || u.correo, 'usuario', u.id, '190.129.10.5', u.fecha_creacion
  FROM usuario u WHERE u.correo LIKE '%@clinicavidasalud.com' AND u.correo <> 'admin@clinicavidasalud.com';
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT u.id, 'CREAR_CUENTA_EN_LINEA', 'Cuenta creada desde el portal', 'usuario', u.id, ('181.115.4.' || (u.id % 200))::inet, u.fecha_creacion
  FROM usuario u JOIN rol r ON r.id = u.rol_id WHERE r.nombre = 'PACIENTE';
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT (SELECT u.id FROM usuario u JOIN persona p ON p.id = u.persona_id WHERE p.ci = '2000001'), 'REGISTRAR_PACIENTE',
       'Paciente ' || pa.nro_expediente || ' registrado en recepción', 'paciente', pa.persona_id, '190.129.10.21', pa.fecha_registro
  FROM paciente pa WHERE NOT EXISTS (SELECT 1 FROM usuario u WHERE u.persona_id = pa.persona_id);
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT c.registrado_por, 'AGENDAR_CITA', 'Cita ' || c.codigo || ' del ' || c.fecha || ' ' || to_char(c.hora_inicio, 'HH24:MI'),
       'cita', c.id, ('190.129.10.' || (20 + c.id % 3))::inet, c.fecha_registro
  FROM cita c;
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT pg.registrado_por, CASE WHEN pg.metodo = 'STRIPE' THEN 'PAGO_APROBADO' ELSE 'PAGO_REGISTRADO' END,
       'Pago de la cita ' || c.codigo || ' (' || pg.metodo || ')', 'pago', pg.id, '190.129.10.21', pg.fecha_pago
  FROM pago pg JOIN cita c ON c.id = pg.cita_id WHERE pg.fecha_pago IS NOT NULL;
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT h.usuario_id, 'CITA_' || h.estado_nuevo, 'Cita ' || c.codigo || ' pasa de ' || h.estado_anterior || ' a ' || h.estado_nuevo,
       'cita', c.id, '190.129.10.22', h.fecha_hora
  FROM historial_estado_cita h JOIN cita c ON c.id = h.cita_id WHERE h.estado_anterior IS NOT NULL;
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora)
SELECT u.id, 'INICIO_SESION', 'Inicio de sesión correcto', 'usuario', u.id, ('190.129.10.' || (u.id % 50))::inet, now() - make_interval(days => d, hours => (u.id % 8)::int)
  FROM usuario u JOIN rol r ON r.id = u.rol_id, generate_series(0, 28, 2) AS d
 WHERE r.nombre IN ('ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO');
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, ip, fecha_hora) VALUES
 (NULL, 'INICIO_SESION_FALLIDO', 'Contraseña incorrecta para jorge.suarez@clinicavidasalud.com', 'usuario', NULL, '200.87.14.3', now() - interval '6 days'),
 (NULL, 'INICIO_SESION_FALLIDO', 'Contraseña incorrecta para jorge.suarez@clinicavidasalud.com', 'usuario', NULL, '200.87.14.3', now() - interval '6 days' + interval '1 minute'),
 ((SELECT u.id FROM usuario u WHERE u.correo = 'carlos.rojas@clinicavidasalud.com'), 'ACCESO_DENEGADO', 'Intento de abrir ?r=reportes sin permiso', NULL, NULL, '190.129.10.31', now() - interval '4 days'),
 ((SELECT u.id FROM usuario u WHERE u.correo = 'juan.perez@example.com'), 'ACCESO_DENEGADO', 'Intento de ver datos del paciente de otra cuenta', NULL, NULL, '181.115.4.9', now() - interval '2 days');

INSERT INTO token_recuperacion (usuario_id, token_hash, fecha_expiracion, usado, fecha_creacion)
SELECT u.id, encode(sha256(('demo-' || u.id)::bytea), 'hex'), now() - interval '9 days' + interval '30 minutes', TRUE, now() - interval '9 days'
  FROM usuario u WHERE u.correo = 'lucia.gomez@example.com';
INSERT INTO token_recuperacion (usuario_id, token_hash, fecha_expiracion, usado, fecha_creacion)
SELECT u.id, encode(sha256(('demo2-' || u.id)::bytea), 'hex'), now() - interval '3 days' + interval '30 minutes', FALSE, now() - interval '3 days'
  FROM usuario u WHERE u.correo = 'roberto.anez@example.com';

COMMIT;

-- Resumen de lo cargado
SELECT 'personas' AS tabla, count(*) FROM persona UNION ALL
SELECT 'pacientes', count(*) FROM paciente UNION ALL
SELECT 'usuarios', count(*) FROM usuario UNION ALL
SELECT 'médicos', count(*) FROM medico UNION ALL
SELECT 'citas', count(*) FROM cita UNION ALL
SELECT 'pagos', count(*) FROM pago UNION ALL
SELECT 'comprobantes', count(*) FROM comprobante UNION ALL
SELECT 'exámenes', count(*) FROM solicitud_examen UNION ALL
SELECT 'notificaciones', count(*) FROM notificacion UNION ALL
SELECT 'bitácora', count(*) FROM bitacora;
