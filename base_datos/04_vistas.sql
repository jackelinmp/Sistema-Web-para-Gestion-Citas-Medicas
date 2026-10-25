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
