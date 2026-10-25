# Ciclos y casos de uso — trazabilidad diagrama ⇄ código

El diagrama de comunicación de cada caso de uso (CU) se lee así en el código:
**IU (boundary) → Control (control) → Entidad (entity) → tabla**.
La fuente única del mapa de ciclos es `config/ciclos.php` (menú, panel y etiqueta CU de cada pantalla salen de ahí).

## Ciclo 1 (abierto): 12 casos de uso, según la documentación (4.2 y 4.5.1)

| Paquete | CU | Pantalla (`?r=`) | Control | Interfaces (IU) | Entidades | Permiso (RBAC) |
|---|---|---|---|---|---|---|
| P1 Seguridad | CU01 Iniciar sesión | `sesion/formulario`, `sesion/recuperar`, `sesion/restablecer`, `sesion/cambiar`, `panel` | `ControlIniciarSesion` | `IUIniciarSesion`, `IURecuperarContrasena`, `IURestablecerContrasena`, `IUCambiarContrasena`, `IUPanel` | Usuario, TokenRecuperacion, ParametroSistema, Bitacora | cualquier usuario |
| P1 Seguridad | CU02 Gestionar cuentas | `cuentas` | `ControlGestionarCuentas` | `IUCuentas`, `IUFormCuenta` | Usuario, Persona, Rol, Bitacora | SEGURIDAD C/R/M/A |
| P1 Seguridad | CU03 Gestionar roles y permisos | `roles`, `roles/guardar` | `ControlGestionarRoles` | `IURoles` | Rol, Bitacora | SEGURIDAD M |
| P1 Seguridad | CU04 Bitácora de auditoría | `bitacora` | `ControlGestionarBitacora` | `IUBitacora` | Bitacora | SEGURIDAD C |
| P2 Pacientes | CU05 Gestionar pacientes | `pacientes` | `ControlGestionarPacientes` | `IUPacientes`, `IUFormPaciente` | Paciente, Persona, FichaClinica, Aseguradora, Usuario, Bitacora | PACIENTES C/R/M |
| P2 Pacientes | CU06 Validar edad y restricciones | `restricciones` (y «include» de CU14) | `ControlValidarRestricciones` | `IURestricciones` | Especialidad, Paciente | CITAS C |
| P3 Especialidades y médicos | CU08 Gestionar especialidades | `especialidades` | `ControlGestionarEspecialidades` | `IUEspecialidades`, `IUFormEspecialidad` | Especialidad, Cita, Bitacora | ESPECIALIDADES_MEDICOS C/R/M/A |
| P3 Especialidades y médicos | CU09 Gestionar médicos | `medicos` | `ControlGestionarMedicos` | `IUMedicos`, `IUFormMedico` | Persona, Medico, Especialidad, Usuario, Bitacora | ESPECIALIDADES_MEDICOS C/R/M |
| P4 Agendas | CU11 Configurar jornada | `jornadas` | `ControlConfigurarJornada` | `IUJornadas` | Medico, Jornada, Cita, Bitacora | AGENDAS C/M |
| P5 Citas | CU13 Verificar disponibilidad | `disponibilidad` | `ControlVerificarDisponibilidad` | `IUDisponibilidad` | Especialidad, Medico, Jornada, Cita | CITAS C |
| P5 Citas | CU14 Agendar cita (incluye CU06 y CU13; «Pagar ahora» extiende a CU19) | `citas/nueva`, `citas/constancia` | `ControlAgendarCita` | `IUAgendarCita`, `IUConstanciaCita` | Paciente, Especialidad, Medico, Cita, Pago, Bitacora | CITAS C/R |
| P7 Pagos | CU19 Procesar pago en línea (Stripe, modo prueba) | `pagos`, `pagos/detalle`, `pagos/iniciar`, `pagos/retorno`, `pagos/cancelado`, `pagos/webhook`, `pagos/seguro`, `pagos/comprobante` | `ControlProcesarPago` (+ `ServicioStripe`, `PdfSimple`) | `IUPagos`, `IUDetallePago` | Pago, Cita, Bitacora | PAGOS C/R |

## Ciclo 2 (bloqueado): 14 casos de uso

CU07, CU10, CU12, CU15, CU16, CU17, CU18, CU20, CU21, CU22, CU23, CU24, CU25 y CU26, agrupados en los paquetes P2 a P10.
La base de datos ya tiene sus tablas, triggers y vistas (`base_datos/`); falta su código.
Aparecen en el menú y en el panel **bloqueados** con el texto «Disponible en el Ciclo 2».

Para abrir un CU del ciclo 2: en `config/ciclos.php` pon su `ruta`, `'listo' => true` y, al empezar el ciclo, `'abierto' => true`. Sus rutas se agregan en `app/rutas.php`.

## Qué ve cada rol (matriz RBAC de `base_datos/03_datos_base.sql`, editable en CU03)

Lo que el rol no puede usar **se muestra bloqueado** (con candado y el motivo), no se oculta; y si el usuario
escribe la dirección a mano, el servidor responde «Acceso no autorizado» (403) y deja el intento en la bitácora.

| Caso de uso | Administrador | Recepcionista | Médico | Paciente |
|---|:-:|:-:|:-:|:-:|
| CU01 Iniciar sesión | ✔ | ✔ | ✔ | ✔ |
| CU02 Cuentas, CU03 Roles, CU04 Bitácora | ✔ | 🔒 | 🔒 | 🔒 |
| CU05 Pacientes | ✔ | ✔ | consultar | 🔒 |
| CU06 Edad y restricciones | ✔ | ✔ | ✔ | ✔ (solo él mismo) |
| CU08 Especialidades, CU09 Médicos | ✔ | consultar | consultar | 🔒 |
| CU11 Jornadas | ✔ | consultar | consultar | 🔒 |
| CU13 Disponibilidad, CU14 Agendar cita | ✔ | ✔ | ✔ | ✔ (solo para sí mismo) |
| CU19 Pago en línea | ✔ | ✔ | 🔒 | ✔ (solo sus citas) |

## Flujo de CU19 (pago en línea)

1. CU14 crea la cita **Programada** con su pago **Pendiente**; la constancia ofrece «Pagar ahora».
2. `pagos/detalle` → «Pagar ahora con tarjeta» → `ServicioStripe::crearSesion` (Stripe Checkout, la tarjeta la captura Stripe).
3. Al volver (`pagos/retorno`) el servidor consulta a Stripe; el webhook (`pagos/webhook`, sin sesión ni CSRF) exige la firma `Stripe-Signature` y el monto exacto. Lo inválido se descarta y queda en la bitácora.
4. Pago aprobado, en una sola transacción: pago **Pagado** (con el id de Stripe), cita **Confirmada**, comprobante `REC-AAAA-NNNNN` y bitácora `PAGO_APROBADO`. Es idempotente.
5. Rechazado o abandonado: el pago sigue **Pendiente** y se puede reintentar. Comprobante en PDF: `pagos/comprobante`.
6. Paciente asegurado: la recepcionista puede «Confirmar por seguro» con la referencia de la póliza.

Variables: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` (ver `.env.example`).

## Flujo de CU01 (iniciar sesión y contraseña)

1. `sesion/formulario` → `iniciarSesion()`: 3 intentos fallidos bloquean la cuenta 15 minutos (el botón «Ingresar» queda deshabilitado) (parámetros `INTENTOS_MAX_LOGIN`, `MINUTOS_BLOQUEO`); cada intento queda en la bitácora.
2. Primer ingreso (`debe_cambiar_contrasena`): se obliga a pasar por `sesion/cambiar`.
3. «¿Olvidó su contraseña?» → `sesion/recuperar` → se genera un token (solo se guarda su hash SHA-256, vence a los 30 min y es de un solo uso) y se envía el enlace por correo (`ServicioCorreo`, variables `MAIL_*`). Sin correo configurado y con `APP_DEBUG=true` el enlace se muestra en pantalla para probar.
4. `sesion/restablecer?token=…` → nueva contraseña (mínimo 8 caracteres, letras y números).
5. Todo evento (inicio, fallo, bloqueo, recuperación, cambio) se registra en la tabla `bitacora`, que no se puede editar ni borrar.
