-- =====================================================================================
--  99_borrar_todo.sql : elimina TODAS las tablas, funciones y secuencias de la aplicación
--  para volver a empezar (después ejecuta 01 → 02 → 03 → 04). ¡Borra los datos!
--  No toca el esquema public ni nada propio de Supabase.
-- =====================================================================================
DROP VIEW IF EXISTS vw_eventos_pendientes, vw_examenes_pendientes, vw_ingresos_diarios, vw_productividad_medico,
                    vw_resumen_paciente, vw_pagos_pendientes, vw_agenda_medico, vw_citas_detalle;
DROP TABLE IF EXISTS mensaje_chat, conversacion, pregunta_frecuente, resultado_laboratorio, solicitud_examen, tipo_examen,
                     evento_paciente, vinculo_familiar, notificacion, comprobante, pago, historial_estado_cita, cita, bloqueo_agenda, jornada,
                     medico_especialidad, medico, especialidad, ficha_clinica, paciente, aseguradora,
                     bitacora, parametro_sistema, token_recuperacion, usuario, rol_permiso, permiso, rol, persona CASCADE;
DROP FUNCTION IF EXISTS sp_registrar_pago(BIGINT, VARCHAR, BIGINT, VARCHAR);
DROP FUNCTION IF EXISTS sp_cambiar_estado_cita(BIGINT, VARCHAR, BIGINT);
DROP FUNCTION IF EXISTS fn_cupos_disponibles(BIGINT, DATE);
DROP FUNCTION IF EXISTS fn_especialidades_permitidas(BIGINT, DATE);
DROP FUNCTION IF EXISTS fn_registrar_bitacora(BIGINT, VARCHAR, TEXT, INET, VARCHAR, BIGINT);
DROP FUNCTION IF EXISTS sp_solicitar_examen(BIGINT, SMALLINT, BIGINT), fn_buscar_faq(TEXT);
DROP FUNCTION IF EXISTS tg_bitacora_solo_insercion(), tg_validar_cita(), tg_historial_estado_cita(), tg_ficha_fecha(), tg_comprobante_solo_pagado(),
                        tg_solicitud_examen_valida(), tg_resultado_listo(), tg_conversacion_cierre(), tg_mensaje_conversacion_activa(), tg_faq_fecha();
DROP SEQUENCE IF EXISTS seq_expediente, seq_cita_codigo, seq_comprobante;
