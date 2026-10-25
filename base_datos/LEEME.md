# Base de datos — Clínica Vida Salud (PostgreSQL / Supabase)

## Instalar (Supabase)
En *SQL Editor → New query* pega **`base_datos_completa.sql`** y pulsa *Run*. Queda lista y **vacía**: solo trae lo mínimo
(4 roles con sus permisos, 3 parámetros y el administrador). Todo lo demás se crea desde el sistema.
**Administrador:** `admin@clinicavidasalud.com` / `Admin123*` (pide cambiar la contraseña al entrar).

## Qué incluye (29 tablas del diagrama de clases, 12 triggers, 8 vistas, 9 funciones)
- **Seguridad:** persona, usuario, rol, permiso, rol_permiso, token_recuperacion, parametro_sistema, bitacora
- **Pacientes:** paciente, ficha_clinica, aseguradora, vinculo_familiar, evento_paciente
- **Catálogo médico:** especialidad, medico, medico_especialidad, jornada, bloqueo_agenda
- **Citas y pagos:** cita, historial_estado_cita, pago, comprobante, notificacion
- **Laboratorio:** tipo_examen, solicitud_examen, resultado_laboratorio
- **Asistente virtual:** conversacion, mensaje_chat, pregunta_frecuente

## Archivos
| Archivo | Qué hace |
|---|---|
| `base_datos_completa.sql` | Todo en uno = 01 + 02 + 03 + 04 + 05 |
| `01_esquema.sql` | Tablas, restricciones (EXCLUDE: sin horarios superpuestos) e índices |
| `02_funciones_triggers.sql` | Funciones, procedimientos y triggers |
| `03_datos_base.sql` | Roles, permisos, parámetros y administrador |
| `04_vistas.sql` | `vw_citas_detalle`, `vw_agenda_medico`, `vw_pagos_pendientes`, `vw_resumen_paciente`, `vw_productividad_medico`, `vw_ingresos_diarios`, `vw_examenes_pendientes`, `vw_eventos_pendientes` |
| `05_supabase_seguridad.sql` | Solo Supabase: activa RLS para cerrar la API pública (la app PHP no se ve afectada) |
| `06_consultas_ejemplo.sql` | Consultas por caso de uso y reportes (solo lectura; no se ejecuta al instalar) |
| `OPCIONAL_datos_de_ejemplo.sql` | **No es necesario.** Población de ejemplo: 10 especialidades, 5 aseguradoras, 2 recepcionistas, 10 médicos, 40 pacientes, ~165 citas con pagos, exámenes, familiares, FAQ y chats. Clave de esos usuarios: `Clinica123*` |
| `99_borrar_todo.sql` | Borra todo lo de la aplicación para empezar de cero |

## Funciones y procedimientos
`fn_registrar_bitacora`, `fn_especialidades_permitidas` (CU06), `fn_cupos_disponibles` (CU13), `fn_buscar_faq`,
`sp_cambiar_estado_cita`, `sp_registrar_pago` (+ comprobante), `sp_solicitar_examen`.

## Triggers
`tg_validar_cita` (fecha futura, paciente/médico activos, edad y sexo), `tg_historial_estado_cita` (guarda el cambio y crea el evento del paciente),
`tg_bitacora_solo_insercion` (RN14), `tg_ficha_fecha`, `tg_comprobante_solo_pagado`, `tg_solicitud_examen_valida`,
`tg_resultado_listo` (marca el examen listo y notifica), `tg_conversacion_cierre`, `tg_mensaje_conversacion_activa`, `tg_faq_fecha`.
Restricciones `ex_cita_medico`, `ex_cita_paciente` y `ex_jornada_superpuesta`: impiden horarios superpuestos.

## Variables de entorno (Vercel)
`DB_HOST` (pooler de Supabase), `DB_PORT=5432`, `DB_NAME=postgres`, `DB_USER=postgres.<ref>`, `DB_PASS`, `DB_SSLMODE=require`.
