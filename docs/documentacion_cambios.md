# Documentación de cambios — Sistema Web de Gestión de Citas Médicas

Versión: `dev-erika` (último commit `8b58fd2` y posteriores).  
Fecha de referencia: 2026-11-06.  
Destinado a: secciones 7 (Implementación) y 8 (Pruebas) del informe.

---

## 1. Arquitectura general

- **Patrón:** MVC ligero. Flujo `IU (boundary) → Control (control) → Entidad (entity) → tabla`.
- **Lenguaje:** PHP 8.x, PostgreSQL (Supabase).
- **Despliegue:** Render con Docker (`Dockerfile`, `render.yaml`). Se retiró Vercel.
- **Autenticación:** sesión por cookie (`SESSION_DRIVER=cookie` en Render). Contraseñas con `bcrypt`.
- **Permisos:** RBAC por módulo/acción (`C` consultar, `R` registrar, `M` modificar, `A` anular).

---

## 2. Ciclo 1 — 12 casos de uso (todos implementados)

| CU | Nombre | Ruta(s) | Control | Entidades | Permiso |
|---|---|---|---|---|---|
| CU01 | Iniciar sesión | `sesion/formulario`, `sesion/iniciar`, `sesion/recuperar`, `sesion/restablecer`, `sesion/cambiar`, `panel` | `ControlIniciarSesion` | Usuario, TokenRecuperacion, ParametroSistema, Bitacora | cualquier |
| CU02 | Cuentas de usuario | `cuentas`, `cuentas/formulario`, `cuentas/guardar`, `cuentas/estado` | `ControlGestionarCuentas` | Usuario, Persona, Rol, Bitacora | SEGURIDAD C/R/M/A |
| CU03 | Roles y permisos | `roles`, `roles/...` | `ControlGestionarRoles` | Rol, Permiso, RolPermiso | SEGURIDAD M |
| CU04 | Bitácora | `bitacora` | `ControlGestionarBitacora` | Bitacora | SEGURIDAD C |
| CU05 | Pacientes | `pacientes`, `pacientes/formulario`, `pacientes/guardar`, `pacientes/estado` | `ControlGestionarPacientes` | Persona, Paciente, FichaClinica, Aseguradora, Bitacora | PACIENTES C/R/M/A |
| CU06 | Edad y restricciones | `restricciones` | `ControlValidarRestricciones` | Especialidad, Paciente | CITAS C |
| CU08 | Especialidades | `especialidades`, `especialidades/formulario`, `especialidades/guardar`, `especialidades/estado` | `ControlGestionarEspecialidades` | Especialidad, Cita, Bitacora | ESPECIALIDADES_MEDICOS C/R/M/A |
| CU09 | Médicos | `medicos`, `medicos/formulario`, `medicos/guardar` | `ControlGestionarMedicos` | Medico, Persona, MedicoEspecialidad, Bitacora | ESPECIALIDADES_MEDICOS C/R/M |
| CU11 | Jornada de atención | `jornadas`, `jornadas/guardar` | `ControlConfigurarJornada` | Jornada, Medico, Bitacora | AGENDAS C/M |
| CU13 | Disponibilidad horaria | `disponibilidad` | `ControlVerificarDisponibilidad` | Medico, Jornada, BloqueoAgenda, Cita | CITAS C |
| CU14 | Agendar cita | `citas/nueva`, `citas/confirmar`, `citas/constancia` | `ControlAgendarCita` | Paciente, Especialidad, Medico, Cita, Pago, Bitacora | CITAS R |
| CU19 | Pago en línea (Stripe) | `pagos`, `pagos/detalle`, `pagos/iniciar`, `pagos/retorno`, `pagos/cancelado`, `pagos/webhook`, `pagos/seguro`, `pagos/comprobante` | `ControlProcesarPago` | Pago, Cita, Bitacora, ServicioStripe, PdfSimple | PAGOS C/R |

### Cambios relevantes en Ciclo 1
- `ControlIniciarSesion::iniciarSesion()` acepta **correo o CI** como usuario (misma contraseña y mismo bloqueo por intentos).
- `ControlIniciarSesion::ingresarExistente()` revalida `persona_id + CI + fecha de nacimiento` antes de crear/abrir la cuenta (la Puerta A ya no confía en el `persona_id` del formulario).
- `ControlAgendarCita` muestra **¿Para quién es la cita?** al rol PACIENTE (él mismo o un familiar de su grupo, CU22); el pago y la constancia aceptan al familiar.
- `ControlAgendarCita::leerParametros()` ahora incluye la clave `origen` (evita notice en `IUAgendarCita`).
- `ControlAgendarCita::confirmarCita()` redirige a `pagos/detalle` (pago obligatorio al reservar).
- `ControlProcesarPago` agregó `registrarPagoManual()` para efectivo/QR/transferencia.
- `IUDetallePago` muestra formulario de pago manual para recepción y copago en seguros.
- `ServicioCorreo` agregó `enviarOtp()` (disponible, no usado actualmente).

---

## 3. Ciclo 2 — 14 casos de uso (todos implementados)

### Grupo 1 (Erika)

#### CU15 — Gestionar Estados de Cita
- **Rutas:** `citas/agenda`, `citas/estado/confirmar`, `citas/estado/atender`
- **Control:** `ControlGestionarEstadosCita`
- **Interfaz:** `IUAgendaDia` (renombrada de `IUAgendaCitas` para coincidir con el PDF p. 133)
- **Entidades:** Cita, Medico, Bitacora
- **Permiso:** CITAS M
- **Acciones:** Confirmar (Programada → Confirmada), Marcar atendida (Confirmada → Atendida, solo si la hora pasó).
- **Cambios:** vista alineada al documento (columnas Código y Motivo, filtro por médico). Médico ve solo su agenda.

#### CU17 — Registrar Inasistencia (extend de CU15)
- **Ruta:** `citas/estado/inasistencia`
- **Control:** `ControlGestionarEstadosCita::marcarInasistencia()`
- **Interfaz:** `IUAgendaDia` (misma pantalla)
- **Acción:** Programada o Confirmada → Inasistencia, cuando la hora ya pasó.
- **Migración:** `01_ciclo2_estados_cita.sql` permite `PROGRAMADA → INASISTENCIA`.

#### CU16 — Reprogramar o cancelar cita
- **Rutas:** `citas/proximas`, `citas/cambiar`, `citas/reprogramar`, `citas/cancelar`
- **Control:** `ControlReprogramarCancelarCita`
- **Interfaces:** `IUMisCitas`, `IUReprogramarCita`
- **Entidades:** Cita, Medico, ParametroSistema, Bitacora
- **Permiso:** CITAS R
- **Reglas:** solo citas Programadas/Confirmadas que no empezaron; el paciente ve y gestiona las suyas y las de su grupo familiar (CU22); 24 h de anticipación y máximo 2 reprogramaciones; cancelar exige motivo; pago pendiente se anula, pagado queda reembolsado.
- **Corrección:** llamada a `notificarEventos()` (inexistente) reemplazada por `notificarCita()` con tipo de evento.

#### CU18 — Reportes operativos
- **Rutas:** `reportes`, `reportes/exportar`
- **Control:** `ControlGenerarReportes`
- **Interfaz:** `IUReportes`
- **Entidades:** Cita, Pago, Bitacora
- **Permiso:** REPORTES C
- **Reportes:** productividad por médico, ingresos diarios, ausentismo por especialidad. Exporta CSV (UTF-8 con BOM para Excel). Período máximo un año.
- **Corrección:** faltaba `Pago::reportarIngresos()`; se agregó.

#### CU24 — Notificaciones y recordatorios
- **Rutas:** `notificaciones`, `notificaciones/leida`, `notificaciones/recordatorios`, `notificaciones/tarea`
- **Control:** `ControlGestionarNotificaciones`
- **Interfaz:** `IUNotificaciones`
- **Entidades:** Notificacion, Usuario, Persona, Bitacora
- **Permiso:** COMUNICACION C
- **Funcionalidad:** bandeja del usuario, marcar leída, generar recordatorios del día siguiente, tarea diaria sin sesión que envía correos pendientes. Las notificaciones de citas de menores se envían al tutor.

#### CU25 — Asistente virtual
- **Rutas:** `asistente`, `asistente/enviar`, `asistente/cerrar`
- **Control:** `ControlAsistenteVirtual`
- **Interfaz:** `IUAsistente`
- **Entidades:** Conversacion, PreguntaFrecuente, Cita
- **Permiso:** COMUNICACION C
- **Funcionalidad:** chat con conversación persistente (sesión), respuestas por IA (ServicioIA con Groq/OpenAI compatible) con fallback a reglas locales. Base de conocimiento (CU26) como contexto. Registra preguntas sin respuesta. Funciona para visitantes sin sesión. Comando de voz vía Web Speech API.

#### CU26 — Base de conocimiento
- **Rutas:** `conocimiento`, `conocimiento/formulario`, `conocimiento/guardar`, `conocimiento/estado`
- **Control:** `ControlGestionarBaseConocimiento`
- **Interfaces:** `IUConocimiento`, `IUFormConocimiento`
- **Entidades:** PreguntaFrecuente, Bitacora, Conversacion
- **Permiso:** COMUNICACION M
- **Funcionalidad:** CRUD de preguntas frecuentes, activar/desactivar, listado de preguntas que el asistente no supo responder.

### Grupo 2 (compañera, integrado en dev-erika)

#### CU07 — Historial del paciente
- **Ruta:** `historial`
- **Control:** `ControlConsultarHistorial`
- **Interfaz:** `IUHistorialPaciente`
- **Entidades:** Cita, Paciente, SolicitudExamen

#### CU10 — Dar de baja a médico
- **Rutas:** `bajas`, `bajas/confirmar`, `bajas/reactivar`
- **Control:** `ControlDarBajaMedico`
- **Interfaz:** `IUBajaMedico`
- **Entidades:** Medico, Cita, Jornada, Usuario, Bitacora

#### CU12 — Bloqueos de agenda
- **Rutas:** `bloqueos`, `bloqueos/formulario`, `bloqueos/guardar`, `bloqueos/quitar`
- **Control:** `ControlGestionarBloqueos`
- **Interfaces:** `IUBloqueos`, `IUFormBloqueo`
- **Entidades:** BloqueoAgenda, Medico, Cita, Bitacora

#### CU20 — Consultar pagos y comprobantes
- **Rutas:** `comprobantes`, `comprobantes/ver`
- **Control:** `ControlConsultarPagos`
- **Interfaces:** `IUHistorialPagos`, `IUComprobante`
- **Entidades:** Pago, VinculoFamiliar, Bitacora

#### CU21 — Ficha clínica básica
- **Rutas:** `ficha`, `ficha/guardar`
- **Control:** `ControlGestionarFichaClinica`
- **Interfaz:** `IUFichaClinica`
- **Entidades:** Paciente, FichaClinica, Bitacora

#### CU22 — Grupo familiar
- **Rutas:** `grupo-familiar`, `grupo-familiar/agregar`, `grupo-familiar/quitar`
- **Control:** `ControlGestionarGrupoFamiliar`
- **Interfaz:** `IUGrupoFamiliar`
- **Entidades:** Paciente, VinculoFamiliar, Persona, Bitacora
- **Efecto:** al vincular un familiar se sincroniza `persona.tutor_id` (avisos al tutor); al quitarlo se recalcula el tutor restante. El titular puede agendar, reprogramar, cancelar y pagar las citas de sus familiares.

#### CU23 — Resultados de laboratorio
- **Rutas:** `laboratorio`, `laboratorio/solicitar`, `laboratorio/solicitar/guardar`, `laboratorio/en-proceso`, `laboratorio/formulario`, `laboratorio/guardar`, `laboratorio/entregar`, `laboratorio/cancelar`, `laboratorio/archivo`
- **Control:** `ControlGestionarResultados`
- **Interfaces:** `IUResultados`, `IUSolicitudExamen`, `IUFormResultado`
- **Entidades:** SolicitudExamen, ResultadoLaboratorio, TipoExamen, Cita, Paciente, Bitacora

---

## 4. Cambios transversales

### Autenticación y pacientes (revisión 2026-11-07)
- **Login** (`sesion/formulario`): usuario = correo **o CI**, con contraseña. Mismo bloqueo 3 intentos / 15 min y recuperación por correo.
- **Crear mi cuenta** (`sesion/registro` → `ControlIniciarSesion::crearCuenta`): solo adultos y solo datos básicos (sin contacto de emergencia ni datos médicos). Si el CI ya es paciente → «Pida su cuenta en recepción».
- **Para otra persona** (`citas/para-otra-persona` → `ControlGestionarGrupoFamiliar::registrarNuevaPersona`): crea paciente + ficha vacía + vínculo; si es menor, `tutor_id` = quien lo registra y, sin CI, usa `CI-del-tutor-1`. Un CI ajeno existente no se vincula: se atiende en recepción.
- Se retiraron «Ya soy paciente (CI + fecha)» y el pre-registro (`sesion/existente*`, `sesion/pre-registro`, `pacientes/pre-registrados`, `pacientes/activar`): permitían entrar a expedientes ajenos conociendo dos datos.
- **Acceso** (`ControlBase::exigirAccesoPaciente`): citas y pagos de todos los que tiene a cargo; historial, ficha y laboratorio solo propios y de menores a cargo.
- **Mi perfil** (`perfil`): celular, dirección, correo, avisos por WhatsApp y contacto de emergencia (opcional). Nombre/CI/fecha solo los corrige recepción.

### UI / Menú
- Los ítems bloqueados (por rol o no implementados) **ya no se muestran** en el menú lateral ni en el panel. Solo aparece lo que el usuario puede usar.

### Dependientes / Tutor
- `vinculo_familiar` (CU22) es la fuente del grupo familiar; al vincular se copia el titular a `persona.tutor_id`.
- Migración 06 agrega `tutor_id` a `persona`.
- Notificaciones (CU24) van a la cuenta del tutor: usan `tutor_id` y, si falta, el vínculo familiar con cuenta habilitada.

### Pagos
- `ControlAgendarCita::confirmarCita()` redirige a `pagos/detalle` (pago obligatorio al reservar).
- Pago manual (efectivo/QR/transferencia) para recepción.
- Copago en seguros médicos.

### Despliegue
- Vercel → Render con Docker.
- `.env` limpiado de basura de Vercel.
- `Config` acepta `DATABASE_URL` como alternativa a `DB_*`.

### Entidades fusionadas (Grupo 1 + Grupo 2)
- `Usuario`: agregado `buscarPorIdentificador` (login por correo o CI).
- `Persona`: agregado `actualizarTutor` (sincroniza CU22 con las notificaciones).
- `Cita`: `listarProximas` acepta un paciente o una lista; agregados `listarPorPaciente`, `listarPendientesPorMedico`, `listarEnPeriodo`, `listarParaExamen`, `motivo_cancelacion`, `veces_reprogramada`.
- `Pago`: `listar` acepta un paciente o una lista; agregado `listarHistorial`, `reportarIngresos`.
- `Medico`: agregado `cambiarEstado`.
- `Jornada`: agregado `cerrarVigente`.
- `FichaClinica`: agregados `obtenerPorPaciente`, `actualizar`, `CAMPOS`, `TIPOS_SANGRE`.
- `Persona`: agregados `buscarPorCi`, `obtenerPorId`, `tutor_id`.

---

## 5. Migraciones (aplicar en orden)

| # | Archivo | Cuándo |
|---|---|---|
| 01 | `01_ciclo2_estados_cita.sql` | CU15/CU17 |
| 02 | `02_ciclo2_grupo1.sql` | CU16/CU18/CU24/CU25/CU26 |
| 03 | `03_ciclo2_copago_seguro.sql` | Copago en seguros |
| 04 | `04_pre_registro_paciente.sql` | Pre-registro |
| 06 | `06_tutor_dependientes.sql` | Dependientes (tutor_id) |
| 07 | `07_resultado_archivo.sql` | Archivo del resultado (CU23) |
| 08 | `08_preguntas_frecuentes_base.sql` | FAQ iniciales (CU26) |
| 09 | `09_catalogos_base.sql` | Aseguradoras y tipos de examen |
| 10 | `10_permisos_ciclo2.sql` | Permisos de los módulos del Ciclo 2 |
| 11 | `11_avisos_whatsapp.sql` | Avisos por WhatsApp (CU24) |
| 12 | `12_responsable_sin_expediente.sql` | El responsable con cuenta no necesita expediente |

Todas idempotentes. Base nueva: `base_datos_completa.sql` + 02, 03, 04, 06, 07, 08, 09, 10, 11 y 12.

---

## 6. Pruebas (resumen)

### Usuarios de prueba
| Rol | Correo | Clave |
|---|---|---|
| Administrador | `admin@clinicavidasalud.com` | `Admin123*` |
| Recepcionista | `recepcion1@clinicavidasalud.com` | `Clinica123*` |
| Médico | `veronica.salazar@clinicavidasalud.com` | `Clinica123*` |
| Paciente | `carla.villarroel@correo.com` | `Clinica123*` |

### Para probar todos los casos de uso
Iniciar sesión como **Administrador** (ve todos los menús). El menú ahora oculta lo que el rol no puede usar.

### Consultas SQL útiles para pruebas
```sql
-- Listar pacientes con edad
SELECT pe.ci, pe.nombres, pe.apellidos,
       EXTRACT(YEAR FROM age(pe.fecha_nacimiento))::int AS edad,
       pe.sexo, p.nro_expediente, p.estado
  FROM paciente p JOIN persona pe ON pe.id = p.persona_id
 ORDER BY pe.apellidos, pe.nombres;

-- Mover cita al pasado (para probar Atender/Inasistencia)
UPDATE cita SET fecha = current_date - 1, hora_inicio = '23:00', hora_fin = '23:30'
 WHERE id = (SELECT id FROM cita WHERE estado = 'CONFIRMADA' ORDER BY fecha LIMIT 1);
```

### Checklist interactivo
`docs/pruebas_completo.html` — casillas con datos de ejemplo y URLs.

---

## 7. Variables de entorno necesarias

### Local (`.env`)
```
APP_DEBUG=true
DB_HOST=aws-0-REGION.pooler.supabase.com
DB_PORT=5432
DB_NAME=postgres
DB_USER=postgres.REFERENCIA
DB_PASS=tu_contraseña
DB_SSLMODE=require
```

### Render (Environment)
```
APP_DEBUG=false
SESSION_DRIVER=cookie
APP_KEY=(generada por render.yaml)
APP_URL=https://tu-servicio.onrender.com
DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_SSLMODE (igual que local)
```

### Opcionales
```
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
MAIL_HOST=smtp.gmail.com (para recuperar contraseña y notificaciones)
AI_API_KEY=gsk_... (para asistente virtual con IA; Groq es gratis)
AI_API_URL=https://api.groq.com/openai/v1
AI_MODEL=llama-3.1-8b-instant
```