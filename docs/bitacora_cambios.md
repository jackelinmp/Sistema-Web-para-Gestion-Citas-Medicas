Bitácora de cambios — Ciclo 2
Registro de todo cambio que toque la base de datos, los archivos compartidos o las reglas
de negocio. Sirve para que el otro equipo sepa qué cambió, por qué y qué parte del
documento hay que actualizar.
Reglas
Todo cambio de esquema va en `base_datos/migraciones/`, numerado y aditivo.
El equipo las aplica a mano en el SQL Editor de Supabase; la aplicación no las ejecuta.
`base_datos_completa.sql` y `config/ciclos.php` tienen un solo dueño (Grupo 1).
Cada fila indica la sección del documento que hay que corregir.
Nada de `DROP`, `RENAME` ni cambios de tipo.
Cambios aplicados
Fecha	CU	Archivo	Cambio	Motivo	Documento
2026-11-05	CU15, CU17	`base_datos/migraciones/01_ciclo2_estados_cita.sql`	`sp_cambiar_estado_cita` acepta `PROGRAMADA → INASISTENCIA`	CU17 permite registrar inasistencia desde Programada o Confirmada; antes solo funcionaba desde Confirmada	4.3 CU17 (flujo principal), 6.2 diagrama de secuencia CU17, 8 casos de prueba
2026-11-05	CU15, CU16, CU17	`base_datos/migraciones/01_ciclo2_estados_cita.sql`, `03_datos_base.sql`	Nuevo permiso `CITAS/M` para Administrador, Recepcionista y Médico	El ciclo 1 solo tenía `CITAS/C` y `CITAS/R`; sin `M` el menú de la agenda queda bloqueado	4.1.1 matriz RBAC, 4.5.1 paquete de citas
2026-11-05	Ciclo 2	`config/ciclos.php`	`abierto => true` y CU15/CU17 con ruta y `listo => true`	El ciclo 2 ya empieza a mostrarse en el menú	4.2 priorización, 4.5.1 paquetes
2026-11-05	CU15, CU17	`app/Controles/Citas/ControlGestionarEstadosCita.php`, `app/Interfaces/Citas/IUAgendaDia.php`, `app/Entidades/Cita.php`, `app/rutas.php`	Agenda del día con Confirmar, Marcar atendida y Registrar inasistencia	Implementación de CU15 y de la extensión CU17	5.2 comunicación, 5.3 clases, 6.2 secuencia, 7 implementación
2026-11-06	CU24, CU25, CU26	`app/Controles/Comunicacion/ControlGestionarNotificaciones.php`, `ControlAsistenteVirtual.php`, `ControlGestionarBaseConocimiento.php`, `app/Interfaces/Comunicacion/*`	Notificaciones, asistente virtual (reglas) y base de conocimiento	Implementación de CU24, CU25 y CU26	5.2 comunicación, 5.3 clases, 6.2 secuencia, 7 implementación
2026-11-05	Despliegue	`Dockerfile`, `.dockerignore`, `render.yaml`, `docs/despliegue_render.md`, `README.md`	Se reemplaza Vercel por Render con Docker; se eliminan `vercel.json`, `api/index.php` y `.vercel/`	Vercel falló con su runtime de PHP y el proyecto requiere el mismo entorno que en local	3.5 herramientas, 7 implementación, anexos (URL y captura)
Cambios previstos
CU	Cambio	Estado
CU16	Reprogramar y cancelar cita; liberar el cupo anterior	Pendiente
CU24	`notificacion.canal` con valor por defecto `PANTALLA`	Pendiente
CU10	`medico.motivo_baja` y `medico.fecha_baja`	Pendiente (Grupo 2)
CU21	`ficha_clinica`: peso, talla, presión y antecedentes gineco-obstétricos	Pendiente (Grupo 2)
CU23	`pago` con vínculo al examen y `resultado_laboratorio.archivo_url`	Pendiente (Grupo 2)
CU25	`cita.origen` acepta `CHATBOT`	Pendiente
Revisión 2026-11-07 (correcciones tras unir las ramas)
Problema	Efecto	Corrección
`ControlIniciarSesion` sin `iniciarSesion()`, `mostrarRegistro()` ni `registrarPaciente()`	Nadie podía iniciar sesión (error 500) ni registrarse	Se restauraron los métodos
`ControlBase` sin `exigirAccesoPaciente()`, `esPaciente()`, `fechaDeFiltro()`, `mensajeBaseDatos()`	Error 500 en CU07, CU20, CU21, CU22 y CU23; error al registrar pago manual	Se agregaron a `ControlBase` (un solo lugar, sin copias)
Líneas `" path="C:\Users\..."` al final de 2 migraciones, 4 pantallas y 1 documento	Las migraciones 04 y 06 fallaban en Supabase; texto basura visible en pantallas	Eliminadas
CU16 usaba el tipo `REPROGRAMACION`, que la regla `notificacion_tipo_check` no admite	Error 500 después de reprogramar	Tipo `SISTEMA`; un aviso ya no puede romper el caso de uso
`notificaciones/tarea` sin clave	Cualquiera podía disparar correos masivos	Exige `CRON_CLAVE` (403 si falta)
Paciente podía abrir CU26 y disparar recordatorios (permiso `C`)	Acceso indebido	CU26 y recordatorios exigen `COMUNICACION/M`
CU25 guardaba la intención solo en la respuesta	La lista «sin respuesta» de CU26 quedaba siempre vacía	La intención se guarda en el mensaje del paciente
CU15 con fecha mal escrita en la URL	Error 500	Vuelve a hoy
CU14 y CU15 no avisaban al paciente	CU24 no cubría «cita registrada / confirmada / inasistencia»	Avisos agregados (también al tutor del menor)
`ServicioIA` con horarios inventados en el prompt	El asistente contradecía la base de conocimiento	Responde solo con la base de conocimiento
`app/schema.sql` suelto y `base_datos/app/` (copia vieja)	Desorden; la instalación no lo mencionaba	Movido a `migraciones/07_resultado_archivo.sql`; copia eliminada; guías de instalación corregidas
Agregado
Comando de voz en todo el sistema (botón «Voz» de la barra): agenda por fecha, buscar paciente, reportes por periodo, abrir cualquier pantalla del menú y preguntar al asistente.
CU18: reporte «Carga horaria y ausencias por médico» y botones Hoy / Esta semana / Este mes.
`docs/metodos_por_control.md`: métodos de cada clase control con su ruta, generado desde el código.