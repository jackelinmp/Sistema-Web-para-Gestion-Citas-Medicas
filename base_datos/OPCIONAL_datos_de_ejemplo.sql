-- =====================================================================================
--  OPCIONAL_datos_de_ejemplo.sql : catálogo y población de ejemplo (NO es necesario para usar el sistema)
--  Ejecutar DESPUÉS de la base completa. Sirve para ver el sistema lleno sin tener que registrar todo a mano.
--   · 10 especialidades y 5 aseguradoras, 2 recepcionistas, 10 médicos, 40 pacientes (6 con portal),
--     jornadas, ~120 citas pasadas y ~45 futuras con sus pagos.  Contraseña de estos usuarios: Clinica123*
--  Para quitarlo todo y volver a una base limpia: 99_borrar_todo.sql y luego base_datos_completa.sql
-- =====================================================================================
INSERT INTO aseguradora (nombre) VALUES
 ('Caja Nacional de Salud'), ('Seguro Universitario (SSU)'), ('Nacional Seguros'),
 ('Alianza Seguros'), ('Seguros Illimani');

INSERT INTO especialidad (nombre, descripcion, costo_consulta, edad_minima, edad_maxima, sexo_permitido) VALUES
 ('Medicina General', 'Atención primaria y control general',            80.00,  0, NULL, 'A'),
 ('Pediatría',        'Atención de niños y adolescentes',              100.00,  0,   14, 'A'),
 ('Ginecología',      'Salud de la mujer',                             150.00, 12, NULL, 'F'),
 ('Cardiología',      'Corazón y sistema circulatorio',                200.00, 18, NULL, 'A'),
 ('Dermatología',     'Enfermedades de la piel',                       120.00,  0, NULL, 'A'),
 ('Traumatología',    'Huesos, músculos y articulaciones',             150.00,  0, NULL, 'A'),
 ('Urología',         'Sistema urinario y salud masculina',            150.00, 14, NULL, 'M'),
 ('Geriatría',        'Atención del adulto mayor',                     120.00, 60, NULL, 'A'),
 ('Oftalmología',     'Salud visual',                                  130.00,  0, NULL, 'A'),
 ('Odontología',      'Salud bucal',                                    90.00,  3, NULL, 'A');


-- ---------- Personas ----------
INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion) VALUES
 ('2000001','Ana','Quispe Mamani','1997-08-21','F','70720120','Av. Villazón #984, La Paz'),
 ('2000002','Luis','Choque Rojas','1992-02-01','M','63443921','Calle Sucre #926, La Paz'),
 ('3000001','Verónica','Salazar Rojas','1980-12-17','F','78675021','Av. Busch #812, La Paz'),
 ('3000002','Ernesto','Calle Vargas','1973-12-31','M','78381502','Calle Ayacucho #779, La Paz'),
 ('3000003','Carolina','Mendoza Arce','1987-12-25','F','74800692','Calle Sucre #12, La Paz'),
 ('3000004','Héctor','Torrez Nina','1984-12-10','M','62708636','Calle Murillo #304, La Paz'),
 ('3000005','Lorena','Peñaranda Suárez','1979-08-31','F','75087912','Av. Arce #844, La Paz'),
 ('3000006','Raúl','Gutiérrez Camacho','1971-02-11','M','70543914','Calle Sucre #416, La Paz'),
 ('3000007','Paula','Limachi Flores','1990-06-14','F','71665461','Calle Potosí #834, La Paz'),
 ('3000008','Gonzalo','Aliaga Zeballos','1982-04-11','M','77233138','Calle Murillo #750, La Paz'),
 ('3000009','Mariela','Rivero Condori','1987-04-29','F','63008657','Av. Villazón #560, La Paz'),
 ('3000010','Walter','Apaza Villarroel','1976-05-24','M','69650319','Calle Ayacucho #154, La Paz'),
 ('4500001','Jimena','Torrez Quispe','2023-04-15','F','72198453','Calle Ayacucho #442, La Paz'),
 ('4500002','Luis','Salazar Limachi','2021-01-10','M','76725377','Calle Murillo #445, La Paz'),
 ('4500003','Teresa','Apaza Villarroel','2019-02-11','F','73359643','Av. Villazón #274, La Paz'),
 ('4500004','Rodrigo','Limachi Aliaga','2017-01-31','M','77380767','Av. Arce #599, La Paz'),
 ('4500005','Daniela','Apaza Choque','2015-09-25','F','77556233','Calle Murillo #744, La Paz'),
 ('4500006','Pablo','Peñaranda Aliaga','2013-08-09','M','76177031','Calle Sucre #880, La Paz'),
 ('4500007','Ana','Arce Suárez','2012-03-01','F','75750652','Av. Villazón #267, La Paz'),
 ('4500008','Ricardo','Salazar Rivero','2010-05-16','M','78392812','Av. Villazón #725, La Paz'),
 ('4500009','Mónica','Salazar Peñaranda','2008-07-09','F','69877557','Av. Camacho #19, La Paz'),
 ('4500010','Carlos','Choque Arce','2003-12-20','M','75375561','Av. Villazón #959, La Paz'),
 ('4500011','Mónica','Fernández Rojas','2002-06-09','F','76292996','Calle Ayacucho #533, La Paz'),
 ('4500012','Mauricio','Peñaranda Zeballos','1999-11-16','M','61128098','Av. Camacho #334, La Paz'),
 ('4500013','Fabiola','Condori Calle','1998-01-30','F','68684932','Calle Ayacucho #954, La Paz'),
 ('4500014','Diego','Peñaranda Nina','1997-02-17','M','62157387','Av. Camacho #934, La Paz'),
 ('4500015','Mónica','Fernández Condori','1995-07-13','F','73801301','Av. 6 de Agosto #470, La Paz'),
 ('4500016','Juan','Flores Quispe','1993-03-03','M','79483045','Av. Villazón #964, La Paz'),
 ('4500017','Carla','Villarroel Rojas','1991-06-16','F','64054009','Calle Comercio #55, La Paz'),
 ('4500018','Ricardo','Mendoza Rivero','1989-11-29','M','71987342','Av. 6 de Agosto #15, La Paz'),
 ('4500019','Silvia','Rivero Limachi','1987-12-31','F','76508408','Av. 6 de Agosto #568, La Paz'),
 ('4500020','Marco','Aliaga Limachi','1986-08-14','M','76935639','Av. Camacho #96, La Paz'),
 ('4500021','Sofía','Rivero Gutiérrez','1984-08-12','F','79663876','Av. Camacho #529, La Paz'),
 ('4500022','Rodrigo','Salazar Choque','1982-01-27','M','66600059','Av. Busch #24, La Paz'),
 ('4500023','Ana','Salazar Flores','1980-09-24','F','71643986','Calle Potosí #966, La Paz'),
 ('4500024','Juan','Quispe Camacho','1978-02-19','M','70997802','Av. Camacho #470, La Paz'),
 ('4500025','Silvia','Condori Aliaga','1976-02-25','F','79180324','Calle Comercio #834, La Paz'),
 ('4500026','Diego','Choque Limachi','1974-01-27','M','66945285','Av. Busch #648, La Paz'),
 ('4500027','Silvia','Calle Villarroel','1972-06-23','F','62332317','Av. Villazón #418, La Paz'),
 ('4500028','Carlos','Rojas Arce','1969-07-24','M','61517888','Av. Villazón #963, La Paz'),
 ('4500029','Lucía','Calle Apaza','1966-11-26','F','66325993','Calle Ayacucho #738, La Paz'),
 ('4500030','Juan','Choque Arce','1965-01-22','M','67339143','Av. Villazón #926, La Paz'),
 ('4500031','Rosa','Gutiérrez Rivero','1963-07-17','F','73085270','Av. Camacho #445, La Paz'),
 ('4500032','Ricardo','Peñaranda Flores','1960-04-07','M','70231057','Av. 6 de Agosto #362, La Paz'),
 ('4500033','Patricia','Mendoza Calle','1958-02-08','F','73949832','Av. Camacho #488, La Paz'),
 ('4500034','Diego','Ramírez Camacho','1956-02-17','M','74917726','Calle Murillo #549, La Paz'),
 ('4500035','Teresa','Mendoza Condori','1954-07-03','F','67762308','Av. Arce #986, La Paz'),
 ('4500036','Luis','Calle Arce','1951-05-04','M','73690290','Av. Villazón #812, La Paz'),
 ('4500037','Patricia','Quispe Fernández','1948-08-01','F','71801151','Av. Villazón #713, La Paz'),
 ('4500038','Iván','Zeballos Rojas','1945-08-25','M','69411087','Av. Villazón #779, La Paz'),
 ('4500039','Jimena','Nina Rojas','1992-01-03','F','69888495','Calle Comercio #284, La Paz'),
 ('4500040','Ricardo','Choque Condori','1999-06-07','M','63235678','Av. Busch #185, La Paz');

-- ---------- Cuentas de usuario (clave de todas: Clinica123*) ----------
INSERT INTO usuario (persona_id, rol_id, correo, contrasena_hash)
SELECT p.id, r.id, v.correo, '$2y$10$RLV.AlqeqC0sbobwgG0q6.Twz.EoPQI..Knx7C0MIldzfxNA2ZRfS'
  FROM (VALUES
   ('2000001','RECEPCIONISTA','recepcion1@clinicavidasalud.com'),
   ('2000002','RECEPCIONISTA','recepcion2@clinicavidasalud.com'),
   ('3000001','MEDICO','veronica.salazar@clinicavidasalud.com'),
   ('3000002','MEDICO','ernesto.calle@clinicavidasalud.com'),
   ('3000003','MEDICO','carolina.mendoza@clinicavidasalud.com'),
   ('3000004','MEDICO','hector.torrez@clinicavidasalud.com'),
   ('3000005','MEDICO','lorena.penaranda@clinicavidasalud.com'),
   ('3000006','MEDICO','raul.gutierrez@clinicavidasalud.com'),
   ('3000007','MEDICO','paula.limachi@clinicavidasalud.com'),
   ('3000008','MEDICO','gonzalo.aliaga@clinicavidasalud.com'),
   ('3000009','MEDICO','mariela.rivero@clinicavidasalud.com'),
   ('3000010','MEDICO','walter.apaza@clinicavidasalud.com'),
   ('4500003','PACIENTE','teresa.apaza@correo.com'),
   ('4500011','PACIENTE','monica.fernandez@correo.com'),
   ('4500013','PACIENTE','fabiola.condori@correo.com'),
   ('4500021','PACIENTE','sofia.rivero@correo.com'),
   ('4500029','PACIENTE','lucia.calle@correo.com'),
   ('4500032','PACIENTE','ricardo.penaranda@correo.com')
  ) AS v(ci, rol, correo)
  JOIN persona p ON p.ci = v.ci JOIN rol r ON r.nombre = v.rol;

-- ---------- Médicos y sus especialidades ----------
INSERT INTO medico (persona_id, matricula)
SELECT p.id, v.mat FROM (VALUES
   ('3000001','MP-1001'),
   ('3000002','MP-1002'),
   ('3000003','MP-1003'),
   ('3000004','MP-1004'),
   ('3000005','MP-1005'),
   ('3000006','MP-1006'),
   ('3000007','MP-1007'),
   ('3000008','MP-1008'),
   ('3000009','MP-1009'),
   ('3000010','MP-1010')
 ) AS v(ci, mat) JOIN persona p ON p.ci = v.ci;

INSERT INTO medico_especialidad (medico_id, especialidad_id)
SELECT p.id, e.id FROM (VALUES
   ('3000001','Medicina General'),
   ('3000002','Medicina General'),
   ('3000002','Geriatría'),
   ('3000003','Pediatría'),
   ('3000004','Pediatría'),
   ('3000004','Medicina General'),
   ('3000005','Ginecología'),
   ('3000006','Cardiología'),
   ('3000007','Dermatología'),
   ('3000008','Traumatología'),
   ('3000008','Urología'),
   ('3000009','Oftalmología'),
   ('3000009','Odontología'),
   ('3000010','Urología'),
   ('3000010','Geriatría')
 ) AS v(ci, esp) JOIN persona p ON p.ci = v.ci JOIN especialidad e ON e.nombre = v.esp;

-- ---------- Jornadas semanales (bloques de 20, 30 o 40 min) ----------
INSERT INTO jornada (medico_id, dia_semana, hora_inicio, hora_fin, duracion_bloque_min)
SELECT p.id, v.dia, CAST(v.ini AS time), CAST(v.fin AS time), v.dur FROM (VALUES
   ('3000001',1,'08:00','12:00',30),
   ('3000001',2,'08:00','12:00',30),
   ('3000001',3,'08:00','12:00',30),
   ('3000001',4,'08:00','12:00',30),
   ('3000001',5,'08:00','12:00',30),
   ('3000002',1,'14:00','18:00',30),
   ('3000002',3,'14:00','18:00',30),
   ('3000002',5,'14:00','18:00',30),
   ('3000003',2,'08:00','13:00',20),
   ('3000003',4,'08:00','13:00',20),
   ('3000004',1,'14:00','18:00',40),
   ('3000004',2,'14:00','18:00',40),
   ('3000004',3,'14:00','18:00',40),
   ('3000004',4,'14:00','18:00',40),
   ('3000004',5,'14:00','18:00',40),
   ('3000005',1,'08:00','12:00',20),
   ('3000005',2,'08:00','12:00',20),
   ('3000005',3,'08:00','12:00',20),
   ('3000005',4,'08:00','12:00',20),
   ('3000005',5,'08:00','12:00',20),
   ('3000006',2,'09:00','13:00',30),
   ('3000006',4,'09:00','13:00',30),
   ('3000006',6,'09:00','13:00',30),
   ('3000007',1,'08:00','12:00',30),
   ('3000007',3,'08:00','12:00',30),
   ('3000007',5,'08:00','12:00',30),
   ('3000008',1,'15:00','19:00',30),
   ('3000008',2,'15:00','19:00',30),
   ('3000008',3,'15:00','19:00',30),
   ('3000008',4,'15:00','19:00',30),
   ('3000008',5,'15:00','19:00',30),
   ('3000009',2,'08:30','12:30',30),
   ('3000009',3,'08:30','12:30',30),
   ('3000009',4,'08:30','12:30',30),
   ('3000009',5,'08:30','12:30',30),
   ('3000010',1,'14:00','18:00',30),
   ('3000010',3,'14:00','18:00',30),
   ('3000010',5,'14:00','18:00',30),
   ('3000010',6,'14:00','18:00',30)
 ) AS v(ci, dia, ini, fin, dur) JOIN persona p ON p.ci = v.ci;

-- ---------- Un bloqueo de agenda de ejemplo (vacaciones próximas del Dr. Torrez) ----------
INSERT INTO bloqueo_agenda (medico_id, fecha_inicio, fecha_fin, tipo_bloqueo, motivo)
SELECT id, (current_date + 9)::timestamp, (current_date + 12)::timestamp, 'VACACIONES', 'Vacaciones programadas' FROM persona WHERE ci = '3000004';

-- ---------- Pacientes y fichas clínicas ----------
INSERT INTO paciente (persona_id, contacto_emergencia_nombre, contacto_emergencia_parentesco, contacto_emergencia_telefono, aseguradora_id, nro_poliza, estado)
SELECT p.id, v.cn, v.par, v.tel, a.id, v.pol, v.estado FROM (VALUES
   ('4500001','Gabriela Torrez','Madre','71953879',NULL,NULL,'ACTIVO'),
   ('4500002','Elena Salazar','Madre','76999571',NULL,NULL,'ACTIVO'),
   ('4500003','Juan Apaza','Hermano/a','62144632','Nacional Seguros','POL-38147','ACTIVO'),
   ('4500004','Juan Limachi','Esposo/a','68594098','Seguro Universitario (SSU)','POL-08242','ACTIVO'),
   ('4500005','María Apaza','Esposo/a','73909260','Alianza Seguros','POL-02023','ACTIVO'),
   ('4500006','Ana Peñaranda','Padre','60771003','Caja Nacional de Salud','POL-40622','ACTIVO'),
   ('4500007','Daniela Arce','Hermano/a','79343494','Caja Nacional de Salud','POL-45916','ACTIVO'),
   ('4500008','Juan Salazar','Hijo/a','61275432',NULL,NULL,'ACTIVO'),
   ('4500009','Diego Salazar','Hermano/a','67235551','Nacional Seguros','POL-77865','ACTIVO'),
   ('4500010','Fernando Choque','Madre','72554285','Alianza Seguros','POL-65702','ACTIVO'),
   ('4500011','Teresa Fernández','Esposo/a','71956045','Alianza Seguros','POL-07554','ACTIVO'),
   ('4500012','Marco Peñaranda','Esposo/a','71091352','Caja Nacional de Salud','POL-37953','ACTIVO'),
   ('4500013','Daniela Condori','Hijo/a','70699921','Nacional Seguros','POL-76757','ACTIVO'),
   ('4500014','Lucía Peñaranda','Esposo/a','66625265','Seguros Illimani','POL-73514','ACTIVO'),
   ('4500015','Ana Fernández','Madre','65439983',NULL,NULL,'ACTIVO'),
   ('4500016','Pablo Flores','Madre','61252208','Caja Nacional de Salud','POL-16474','ACTIVO'),
   ('4500017','Rodrigo Villarroel','Hermano/a','70141530','Nacional Seguros','POL-03775','ACTIVO'),
   ('4500018','Marco Mendoza','Padre','71944419','Seguros Illimani','POL-54434','ACTIVO'),
   ('4500019','Miguel Rivero','Padre','74125927','Alianza Seguros','POL-86848','ACTIVO'),
   ('4500020','Fabiola Aliaga','Padre','66156134',NULL,NULL,'ACTIVO'),
   ('4500021','Daniel Rivero','Hermano/a','78619292','Caja Nacional de Salud','POL-52018','ACTIVO'),
   ('4500022','Teresa Salazar','Padre','67658993',NULL,NULL,'ACTIVO'),
   ('4500023','Lucía Salazar','Esposo/a','78157980','Caja Nacional de Salud','POL-62763','ACTIVO'),
   ('4500024','Jorge Quispe','Esposo/a','64652571','Nacional Seguros','POL-82154','ACTIVO'),
   ('4500025','Ricardo Condori','Padre','78555000','Caja Nacional de Salud','POL-04982','ACTIVO'),
   ('4500026','Álvaro Choque','Esposo/a','71432431',NULL,NULL,'ACTIVO'),
   ('4500027','Rodrigo Calle','Esposo/a','61887629',NULL,NULL,'ACTIVO'),
   ('4500028','María Rojas','Padre','75788872',NULL,NULL,'ACTIVO'),
   ('4500029','Ana Calle','Hermano/a','65061579','Seguro Universitario (SSU)','POL-97674','ACTIVO'),
   ('4500030','Diego Choque','Esposo/a','67801832',NULL,NULL,'ACTIVO'),
   ('4500031','Mauricio Gutiérrez','Esposo/a','71532253',NULL,NULL,'ACTIVO'),
   ('4500032','Paola Peñaranda','Esposo/a','75410467','Nacional Seguros','POL-83495','ACTIVO'),
   ('4500033','Fernando Mendoza','Hijo/a','79481022','Seguros Illimani','POL-40920','ACTIVO'),
   ('4500034','Paola Ramírez','Hijo/a','65159988','Seguro Universitario (SSU)','POL-63552','ACTIVO'),
   ('4500035','María Mendoza','Hermano/a','73701176','Nacional Seguros','POL-04917','ACTIVO'),
   ('4500036','Elena Calle','Esposo/a','72508886','Caja Nacional de Salud','POL-71661','ACTIVO'),
   ('4500037','Teresa Quispe','Madre','60615805','Seguro Universitario (SSU)','POL-69916','ACTIVO'),
   ('4500038','Sofía Zeballos','Hermano/a','70291497',NULL,NULL,'ACTIVO'),
   ('4500039','Gustavo Nina','Hijo/a','72770208','Caja Nacional de Salud','POL-70646','INACTIVO'),
   ('4500040','Rodrigo Choque','Padre','77071202',NULL,NULL,'ACTIVO')
 ) AS v(ci, cn, par, tel, aseg, pol, estado)
 JOIN persona p ON p.ci = v.ci LEFT JOIN aseguradora a ON a.nombre = v.aseg ORDER BY p.ci;

INSERT INTO ficha_clinica (paciente_id) SELECT persona_id FROM paciente;
UPDATE ficha_clinica f SET
       tipo_sangre = (ARRAY['O+','O+','O+','A+','A+','B+','AB+','O-','A-'])[1 + (f.paciente_id % 9)::int],
       alergias    = (ARRAY['No especificado','Ninguna conocida','Penicilina','Polen','Mariscos'])[1 + (f.paciente_id % 5)::int],
       enfermedades_cronicas = (ARRAY['No especificado','Ninguna','Hipertensión','Diabetes tipo 2','Asma'])[1 + ((f.paciente_id / 2) % 5)::int]
 WHERE f.paciente_id % 3 <> 0;

-- ---------- Citas de prueba: pasadas (historial) y futuras (agenda) ----------
DO $$
DECLARE
    v_admin  BIGINT := (SELECT id FROM usuario WHERE correo = 'admin@clinicavidasalud.com');
    v_recep  BIGINT := (SELECT u.id FROM usuario u JOIN persona p ON p.id = u.persona_id WHERE p.ci = '2000001');
    r        RECORD;
    v_cita   BIGINT;
    v_estado TEXT;
    v_n      INT;
    v_intentos INT;
    v_fecha  DATE;
    v_ini    TIME;
    v_dur    INT;
    v_paciente BIGINT;
    v_esp    SMALLINT;
    v_metodo TEXT;
    v_creados_pasadas INT := 0;
    v_creados_futuras INT := 0;
BEGIN
    PERFORM setseed(0.42);
    PERFORM set_config('app.usuario_id', v_recep::text, TRUE);
    ALTER TABLE cita DISABLE TRIGGER tg_validar_cita;     -- las citas pasadas no pasan por la validación de fecha

    -- ============ PASADAS: hasta 120 citas en los últimos 45 días ============
    v_intentos := 0;
    WHILE v_creados_pasadas < 120 AND v_intentos < 4000 LOOP
        v_intentos := v_intentos + 1;
        v_fecha := current_date - (1 + floor(random() * 45))::int;
        -- jornada al azar de un médico que atienda ese día
        SELECT j.medico_id, j.hora_inicio, j.hora_fin, j.duracion_bloque_min
          INTO r
          FROM jornada j
         WHERE j.vigente AND j.dia_semana = EXTRACT(ISODOW FROM v_fecha)
         ORDER BY random() LIMIT 1;
        CONTINUE WHEN r.medico_id IS NULL;
        v_dur := r.duracion_bloque_min;
        v_n   := floor(random() * (EXTRACT(EPOCH FROM (r.hora_fin - r.hora_inicio)) / 60 / v_dur))::int;
        v_ini := r.hora_inicio + make_interval(mins => v_n * v_dur);
        -- paciente activo y especialidad del médico permitida para su edad y sexo
        SELECT pa.persona_id, f.id INTO v_paciente, v_esp
          FROM paciente pa
          JOIN fn_especialidades_permitidas(pa.persona_id, v_fecha) f ON TRUE
          JOIN medico_especialidad me ON me.medico_id = r.medico_id AND me.especialidad_id = f.id
         WHERE pa.estado = 'ACTIVO'
         ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_paciente IS NULL;
        BEGIN
            INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta, origen, registrado_por)
            VALUES (v_paciente, r.medico_id, v_esp, v_fecha, v_ini, v_ini + make_interval(mins => v_dur),
                    (ARRAY['Control general','Dolor persistente','Revisión de resultados','Primera consulta','Seguimiento','Chequeo anual'])[1 + floor(random()*6)::int],
                    'RECEPCION', v_recep)
            RETURNING id INTO v_cita;
        EXCEPTION WHEN exclusion_violation THEN
            CONTINUE;
        END;
        v_estado := CASE WHEN random() < 0.70 THEN 'ATENDIDA' WHEN random() < 0.5 THEN 'CANCELADA' ELSE 'INASISTENCIA' END;
        IF v_estado = 'ATENDIDA' THEN
            UPDATE cita SET estado = 'CONFIRMADA' WHERE id = v_cita;
        END IF;
        UPDATE cita SET estado = v_estado WHERE id = v_cita;
        INSERT INTO pago (cita_id, monto, estado, registrado_por, fecha_registro)
        SELECT c.id, e.costo_consulta, 'PENDIENTE', v_recep, c.fecha::timestamp
          FROM cita c JOIN especialidad e ON e.id = c.especialidad_id WHERE c.id = v_cita;
        IF v_estado = 'ATENDIDA' THEN
            v_metodo := (ARRAY['EFECTIVO','QR','TARJETA','TRANSFERENCIA'])[1 + floor(random()*4)::int];
            PERFORM sp_registrar_pago(v_cita, v_metodo, v_recep);
        ELSIF v_estado = 'CANCELADA' THEN
            UPDATE pago SET estado = 'ANULADO' WHERE cita_id = v_cita;
        END IF;
        INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, fecha_hora)
        SELECT v_recep, 'AGENDAR_CITA', 'Cita ' || c.codigo || ' del ' || c.fecha || ' ' || c.hora_inicio, 'cita', c.id,
               c.fecha::timestamp - interval '2 days'
          FROM cita c WHERE c.id = v_cita;
        v_creados_pasadas := v_creados_pasadas + 1;
    END LOOP;

    ALTER TABLE cita ENABLE TRIGGER tg_validar_cita;      -- las futuras SÍ se validan con el trigger

    -- ============ FUTURAS: hasta 45 citas en los próximos 20 días (usa los cupos reales) ============
    v_intentos := 0;
    WHILE v_creados_futuras < 45 AND v_intentos < 4000 LOOP
        v_intentos := v_intentos + 1;
        v_fecha := current_date + (1 + floor(random() * 20))::int;
        SELECT j.medico_id INTO r FROM jornada j
         WHERE j.vigente AND j.dia_semana = EXTRACT(ISODOW FROM v_fecha) ORDER BY random() LIMIT 1;
        CONTINUE WHEN r.medico_id IS NULL;
        SELECT c.hora_inicio INTO v_ini
          FROM fn_cupos_disponibles(r.medico_id, v_fecha) c ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_ini IS NULL;
        SELECT pa.persona_id, f.id INTO v_paciente, v_esp
          FROM paciente pa
          JOIN fn_especialidades_permitidas(pa.persona_id, v_fecha) f ON TRUE
          JOIN medico_especialidad me ON me.medico_id = r.medico_id AND me.especialidad_id = f.id
         WHERE pa.estado = 'ACTIVO'
         ORDER BY random() LIMIT 1;
        CONTINUE WHEN v_paciente IS NULL;
        BEGIN
            INSERT INTO cita (paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin, motivo_consulta, origen, registrado_por)
            SELECT v_paciente, r.medico_id, v_esp, v_fecha, c.hora_inicio, c.hora_fin,
                   (ARRAY['Control general','Dolor persistente','Revisión de resultados','Primera consulta','Seguimiento'])[1 + floor(random()*5)::int],
                   (ARRAY['RECEPCION','RECEPCION','PORTAL'])[1 + floor(random()*3)::int], v_recep
              FROM fn_cupos_disponibles(r.medico_id, v_fecha) c
             WHERE c.hora_inicio = v_ini
            RETURNING id INTO v_cita;
        EXCEPTION WHEN exclusion_violation THEN
            CONTINUE;
        END;
        IF random() < 0.35 THEN
            UPDATE cita SET estado = 'CONFIRMADA' WHERE id = v_cita;
        END IF;
        INSERT INTO pago (cita_id, monto, estado, registrado_por)
        SELECT c.id, e.costo_consulta, 'PENDIENTE', v_recep
          FROM cita c JOIN especialidad e ON e.id = c.especialidad_id WHERE c.id = v_cita;
        INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id)
        SELECT v_recep, 'AGENDAR_CITA', 'Cita ' || c.codigo || ' del ' || c.fecha || ' ' || c.hora_inicio, 'cita', c.id
          FROM cita c WHERE c.id = v_cita;
        v_creados_futuras := v_creados_futuras + 1;
    END LOOP;

    RAISE NOTICE 'Citas de prueba creadas: % pasadas, % futuras', v_creados_pasadas, v_creados_futuras;
END $$;

-- ---------- Algo de actividad en la bitácora ----------
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, fecha_hora)
SELECT u.id, 'INICIO_SESION', 'Inicio de sesión correcto', 'usuario', u.id, now() - (n || ' hours')::interval
  FROM usuario u, generate_series(1, 30, 7) AS n
 WHERE u.rol_id IN (SELECT id FROM rol WHERE nombre IN ('ADMINISTRADOR','RECEPCIONISTA'));
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, fecha_hora)
SELECT NULL, 'INICIO_SESION_FALLIDO', 'Contraseña incorrecta para ' || correo, 'usuario', id, now() - interval '3 days'
  FROM usuario WHERE correo LIKE 'recepcion2%';
INSERT INTO bitacora (usuario_id, accion, descripcion, tabla, registro_id, fecha_hora)
SELECT (SELECT id FROM usuario WHERE correo = 'admin@clinicavidasalud.com'), 'CREAR_USUARIO',
       'Cuenta creada para ' || u.correo, 'usuario', u.id, now() - interval '20 days'
  FROM usuario u WHERE u.correo LIKE '%@clinicavidasalud.com' AND u.correo <> 'admin@clinicavidasalud.com';

ANALYZE;

-- =====================================================================================
--  Ciclo 2 de ejemplo: exámenes, familiares, preguntas frecuentes y chat
-- =====================================================================================
INSERT INTO tipo_examen (nombre, descripcion, costo) VALUES
 ('Hemograma completo',        'Recuento de células de la sangre',          45.00),
 ('Glucosa en ayunas',         'Control de azúcar en sangre',               25.00),
 ('Perfil lipídico',           'Colesterol y triglicéridos',                80.00),
 ('Examen general de orina',   'Análisis físico, químico y microscópico',   30.00),
 ('Radiografía de tórax',      'Imagen del tórax',                         120.00),
 ('Electrocardiograma',        'Actividad eléctrica del corazón',           90.00),
 ('Ecografía abdominal',       'Imagen por ultrasonido del abdomen',       150.00),
 ('Prueba de embarazo',        'Detección de hCG en sangre',                35.00);

INSERT INTO vinculo_familiar (paciente_id, familiar_id, parentesco)
SELECT t.id, f.id, v.par
  FROM (VALUES ('4500017','4500001','HIJO'),('4500019','4500002','HIJO'),('4500021','4500003','HIJO'),
               ('4500023','4500004','HIJO'),('4500025','4500005','HIJO'),('4500030','4500031','CONYUGE'),
               ('4500034','4500032','HERMANO')) AS v(ci_t, ci_f, par)
  JOIN persona t ON t.ci = v.ci_t JOIN persona f ON f.ci = v.ci_f;

INSERT INTO pregunta_frecuente (categoria, pregunta, respuesta, palabras_clave) VALUES
 ('CITAS',    '¿Cómo agendo una cita médica?', 'Ingrese al sistema, elija "Agendar cita", seleccione especialidad, médico y un horario disponible.', 'agendar cita reservar turno'),
 ('CITAS',    '¿Puedo cancelar o reprogramar mi cita?', 'Sí, hasta 24 horas antes desde "Mis citas" o llamando a recepción.', 'cancelar reprogramar cambiar cita'),
 ('CITAS',    '¿Qué pasa si no puedo asistir?', 'Avise con anticipación. Las inasistencias repetidas pueden limitar nuevas reservas.', 'inasistencia faltar no asistir'),
 ('CITAS',    '¿Con cuánta anticipación puedo agendar?', 'Puede agendar desde hoy hasta 60 días hacia adelante, según la agenda del médico.', 'anticipación fecha disponible'),
 ('PAGOS',    '¿Qué métodos de pago aceptan?', 'Efectivo, tarjeta, QR, transferencia y pago en línea.', 'pago pagar tarjeta qr efectivo'),
 ('PAGOS',    '¿Recibiré un comprobante?', 'Sí, al confirmar el pago se emite un comprobante con número correlativo.', 'comprobante factura recibo'),
 ('EXAMENES', '¿Cómo veo mis resultados de laboratorio?', 'Ingrese a "Mis exámenes"; le avisaremos por correo cuando estén listos.', 'resultados laboratorio examen'),
 ('EXAMENES', '¿Necesito ayuno para los análisis?', 'Para glucosa y perfil lipídico se recomiendan 8 a 12 horas de ayuno.', 'ayuno análisis sangre'),
 ('CUENTA',   '¿Olvidé mi contraseña, qué hago?', 'En la pantalla de inicio pulse "¿Olvidó su contraseña?" y siga el enlace que llegará a su correo.', 'contraseña olvidé recuperar clave'),
 ('CUENTA',   '¿Por qué mi cuenta está bloqueada?', 'Tras varios intentos fallidos se bloquea unos minutos. Espere o recupere su contraseña.', 'bloqueada cuenta intentos'),
 ('GENERAL',  '¿Cuál es el horario de atención?', 'Lunes a viernes de 08:00 a 19:00 y sábados de 08:00 a 13:00.', 'horario atención abierto'),
 ('GENERAL',  '¿Dónde está la clínica?', 'Contamos con atención en La Paz, Bolivia. Consulte la dirección exacta en recepción.', 'dirección ubicación donde');

DO $$
DECLARE
    v_recep BIGINT := (SELECT u.id FROM usuario u JOIN persona p ON p.id = u.persona_id WHERE p.ci = '2000001');
    r RECORD; v_sol BIGINT; v_conv UUID; n INT := 0;
BEGIN
    PERFORM setseed(0.37);
    -- exámenes en ~30 citas atendidas; 75 % ya con resultado (el trigger los marca listos y notifica)
    FOR r IN SELECT c.id AS cita_id FROM cita c WHERE c.estado = 'ATENDIDA' ORDER BY random() LIMIT 30 LOOP
        v_sol := sp_solicitar_examen(r.cita_id, (1 + floor(random() * 8))::smallint, v_recep);
        n := n + 1;
        IF random() < 0.75 THEN
            INSERT INTO resultado_laboratorio (solicitud_examen_id, resultado, archivo_nombre, tipo_archivo, tamano_bytes)
            VALUES (v_sol, 'Valores dentro de los rangos de referencia.', 'resultado_' || v_sol || '.pdf', 'PDF', 150000 + (random() * 100000)::int);
            IF random() < 0.6 THEN UPDATE solicitud_examen SET estado = 'ENTREGADO' WHERE id = v_sol; END IF;
        ELSIF random() < 0.5 THEN
            UPDATE solicitud_examen SET estado = 'EN_PROCESO' WHERE id = v_sol;
        END IF;
    END LOOP;

    -- conversaciones del asistente virtual (4 de pacientes, 2 de visitantes; 4 cerradas)
    FOR r IN SELECT p.id AS paciente, row_number() OVER (ORDER BY p.id) AS k
               FROM persona p WHERE p.ci IN ('4500003','4500011','4500021','4500029') LOOP
        INSERT INTO conversacion (paciente_id, ip) VALUES (r.paciente, ('190.129.10.' || (10 + r.k))::inet) RETURNING id INTO v_conv;
        INSERT INTO mensaje_chat (conversacion_id, emisor, texto, intencion) VALUES
          (v_conv, 'PACIENTE',  'Hola, quiero agendar una cita', 'AGENDAR'),
          (v_conv, 'ASISTENTE', 'Con gusto. ¿Para qué especialidad desea la cita?', 'AGENDAR'),
          (v_conv, 'PACIENTE',  '¿Qué métodos de pago aceptan?', 'FAQ'),
          (v_conv, 'ASISTENTE', 'Efectivo, tarjeta, QR, transferencia y pago en línea.', 'FAQ');
        IF r.k <= 3 THEN UPDATE conversacion SET estado = 'CERRADA' WHERE id = v_conv; END IF;
    END LOOP;
    FOR r IN SELECT g AS k FROM generate_series(1, 2) g LOOP
        INSERT INTO conversacion (ip) VALUES (('181.115.20.' || (30 + r.k))::inet) RETURNING id INTO v_conv;
        INSERT INTO mensaje_chat (conversacion_id, emisor, texto, intencion) VALUES
          (v_conv, 'PACIENTE',  '¿Cuál es el horario de atención?', 'FAQ'),
          (v_conv, 'ASISTENTE', 'Lunes a viernes de 08:00 a 19:00 y sábados de 08:00 a 13:00.', 'FAQ');
        UPDATE conversacion SET estado = 'CERRADA' WHERE id = v_conv;
    END LOOP;
    RAISE NOTICE 'Ciclo 2 de ejemplo: % solicitudes de examen', n;
END $$;
ANALYZE;
