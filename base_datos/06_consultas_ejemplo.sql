-- =====================================================================================
--  06_consultas_ejemplo.sql : consultas útiles por caso de uso y reportes (solo lectura)
--  No se ejecuta en la instalación: ábrelo en el SQL Editor y corre la consulta que necesites.
--  Los valores entre comillas (CI, fechas, ids) son ejemplos: cámbialos por los tuyos.
-- =====================================================================================

-- ======================= CU01 / CU02 · Cuentas y acceso =================================
-- Cuentas con su rol, estado y último acceso
SELECT u.id, u.correo, r.nombre AS rol, u.estado, u.ultimo_acceso, p.nombres || ' ' || p.apellidos AS persona
  FROM usuario u JOIN rol r ON r.id = u.rol_id JOIN persona p ON p.id = u.persona_id ORDER BY r.id, p.apellidos;

-- Cuentas bloqueadas ahora mismo
SELECT correo, intentos_fallidos, bloqueado_hasta FROM usuario WHERE bloqueado_hasta > now();

-- ======================= CU03 · Roles y permisos ========================================
-- Matriz de permisos por rol
SELECT r.nombre AS rol, p.modulo, string_agg(p.accion, ' ' ORDER BY p.accion) AS acciones
  FROM rol r JOIN rol_permiso rp ON rp.rol_id = r.id JOIN permiso p ON p.id = rp.permiso_id
 GROUP BY r.nombre, p.modulo ORDER BY r.nombre, p.modulo;

-- ======================= CU04 · Bitácora ================================================
-- Últimos 50 eventos
SELECT b.fecha_hora, b.accion, u.correo, b.descripcion, host(b.ip) AS ip
  FROM bitacora b LEFT JOIN usuario u ON u.id = b.usuario_id ORDER BY b.fecha_hora DESC, b.id DESC LIMIT 50;
-- Eventos por acción en los últimos 30 días
SELECT accion, count(*) AS veces FROM bitacora WHERE fecha_hora >= now() - interval '30 days' GROUP BY accion ORDER BY veces DESC;

-- ======================= CU05 · Pacientes ===============================================
-- Buscar por CI, nombre o expediente
SELECT * FROM vw_resumen_paciente
 WHERE ci ILIKE '%4500%' OR paciente ILIKE '%mamani%' OR nro_expediente ILIKE '%EXP-000010%';
-- Pacientes sin ninguna cita
SELECT paciente, nro_expediente FROM vw_resumen_paciente WHERE citas_total = 0;
-- Pacientes con aseguradora, por aseguradora
SELECT coalesce(aseguradora, 'Sin seguro') AS aseguradora, count(*) FROM vw_resumen_paciente GROUP BY 1 ORDER BY 2 DESC;

-- ======================= CU06 · Edad y restricciones ====================================
-- Especialidades que puede tomar un paciente en una fecha
SELECT * FROM fn_especialidades_permitidas((SELECT id FROM persona WHERE ci = '4500003'), current_date + 3);

-- ======================= CU08 / CU09 · Especialidades y médicos ========================
SELECT e.nombre, e.costo_consulta, e.edad_minima, e.edad_maxima, e.sexo_permitido, e.estado, count(me.medico_id) AS medicos
  FROM especialidad e LEFT JOIN medico_especialidad me ON me.especialidad_id = e.id GROUP BY e.id ORDER BY e.nombre;

SELECT p.nombres || ' ' || p.apellidos AS medico, m.matricula, m.estado, string_agg(e.nombre, ', ' ORDER BY e.nombre) AS especialidades
  FROM medico m JOIN persona p ON p.id = m.persona_id
  LEFT JOIN medico_especialidad me ON me.medico_id = m.persona_id LEFT JOIN especialidad e ON e.id = me.especialidad_id
 GROUP BY p.id, m.matricula, m.estado ORDER BY p.apellidos;

-- ======================= CU11 · Jornadas ================================================
SELECT p.nombres || ' ' || p.apellidos AS medico,
       (ARRAY['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'])[j.dia_semana] AS dia, j.hora_inicio, j.hora_fin, j.duracion_bloque_min
  FROM jornada j JOIN persona p ON p.id = j.medico_id WHERE j.vigente ORDER BY p.apellidos, j.dia_semana;

-- ======================= CU13 · Disponibilidad ==========================================
-- Cupos libres de un médico en una fecha (cambia el CI del médico y la fecha)
SELECT c.* FROM fn_cupos_disponibles((SELECT id FROM persona WHERE ci = '3000001'), current_date + 2) c;
-- Cupos libres de TODOS los médicos de una especialidad en una fecha
SELECT p.nombres || ' ' || p.apellidos AS medico, c.hora_inicio, c.hora_fin
  FROM medico_especialidad me JOIN especialidad e ON e.id = me.especialidad_id AND e.nombre = 'Medicina General'
  JOIN persona p ON p.id = me.medico_id
  CROSS JOIN LATERAL fn_cupos_disponibles(me.medico_id, current_date + 2) c ORDER BY medico, c.hora_inicio;

-- ======================= CU14 · Citas ===================================================
-- Agenda de hoy
SELECT hora_inicio, medico, paciente, especialidad, estado FROM vw_citas_detalle WHERE fecha = current_date ORDER BY hora_inicio;
-- Citas futuras de un paciente
SELECT codigo, fecha, hora_inicio, medico, especialidad, estado FROM vw_citas_detalle
 WHERE paciente_ci = '4500003' AND fecha >= current_date ORDER BY fecha, hora_inicio;
-- Historial de estados de una cita
SELECT h.fecha_hora, h.estado_anterior, h.estado_nuevo, u.correo
  FROM historial_estado_cita h LEFT JOIN usuario u ON u.id = h.usuario_id
 WHERE h.cita_id = (SELECT id FROM cita ORDER BY id DESC LIMIT 1) ORDER BY h.fecha_hora, h.id;

-- ======================= CU19 · Pagos ===================================================
SELECT * FROM vw_pagos_pendientes ORDER BY fecha;
SELECT * FROM vw_ingresos_diarios LIMIT 15;
-- Total cobrado por especialidad
SELECT e.nombre, count(*) AS pagos, sum(pg.monto) AS total
  FROM pago pg JOIN cita c ON c.id = pg.cita_id JOIN especialidad e ON e.id = c.especialidad_id
 WHERE pg.estado = 'PAGADO' GROUP BY e.nombre ORDER BY total DESC;

-- ======================= Exámenes (Ciclo 2) =============================================
SELECT * FROM vw_examenes_pendientes ORDER BY fecha_solicitud;
SELECT t.nombre, count(*) AS solicitudes, sum(s.costo) AS monto FROM solicitud_examen s JOIN tipo_examen t ON t.id = s.tipo_examen_id GROUP BY t.nombre ORDER BY 2 DESC;

-- ======================= Reportes de gestión ============================================
-- Citas por mes y estado
SELECT to_char(fecha, 'YYYY-MM') AS mes, estado, count(*) FROM cita GROUP BY 1, 2 ORDER BY 1 DESC, 2;
-- Productividad e inasistencias por médico (30 días)
SELECT * FROM vw_productividad_medico ORDER BY citas DESC;
-- Ocupación por día de la semana
SELECT (ARRAY['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'])[EXTRACT(ISODOW FROM fecha)::int] AS dia, count(*) AS citas
  FROM cita WHERE estado IN ('ATENDIDA','PROGRAMADA','CONFIRMADA') GROUP BY EXTRACT(ISODOW FROM fecha) ORDER BY EXTRACT(ISODOW FROM fecha);
-- Horas más demandadas
SELECT extract(hour FROM hora_inicio)::int AS hora, count(*) AS citas FROM cita GROUP BY 1 ORDER BY 1;
-- Especialidades más solicitadas
SELECT e.nombre, count(*) AS citas FROM cita c JOIN especialidad e ON e.id = c.especialidad_id GROUP BY e.nombre ORDER BY citas DESC;
-- Origen de las citas (recepción / médico / portal)
SELECT origen, count(*) FROM cita GROUP BY origen ORDER BY 2 DESC;
-- Tasa de cancelación e inasistencia general
SELECT round(100.0 * count(*) FILTER (WHERE estado = 'CANCELADA') / count(*), 1)    AS pct_canceladas,
       round(100.0 * count(*) FILTER (WHERE estado = 'INASISTENCIA') / count(*), 1) AS pct_inasistencia FROM cita;

-- ======================= Asistente virtual ==============================================
SELECT * FROM fn_buscar_faq('pago');
SELECT c.fecha_inicio, c.estado, count(m.id) AS mensajes FROM conversacion c LEFT JOIN mensaje_chat m ON m.conversacion_id = c.id GROUP BY c.id ORDER BY c.fecha_inicio DESC;
SELECT * FROM vw_eventos_pendientes LIMIT 20;
