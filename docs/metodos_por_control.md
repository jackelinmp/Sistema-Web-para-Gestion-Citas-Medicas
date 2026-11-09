# Métodos por control

Generado a partir del código (`app/Controles`). Sirve para comparar con los diagramas de comunicación y de clases: cada método público es un mensaje IU → Control; las rutas están en `app/rutas.php`.

## ControlConfigurarJornada — CU11 Configurar Jornada de Atención (RF05).   Responsable: Jackeline
- `mostrarJornada()` — `jornadas` (GET)
- `guardarJornada()` — `jornadas/guardar` (POST)

## ControlGestionarBloqueos — CU12 Gestionar Bloqueos de Agenda (RF05).   Ciclo 2 · P4 · Responsable: Jackeline
- `listarBloqueos()` — `bloqueos` (GET)
- `mostrarFormulario()` — `bloqueos/formulario` (GET)
- `guardarBloqueo()` — `bloqueos/guardar` (POST)
- `quitarBloqueo()` — `bloqueos/quitar` (POST)

## ControlAgendarCita — CU14 Agendar Cita Médica (RF06, RF07).   Responsable: Jackeline
- `mostrarFormulario()` — `citas/nueva` (GET)
- `seleccionarParaMi()` — `citas/para-mi` (POST)
- `mostrarResumen()` — llamado por otro control («include»)
- `confirmarCita()` — `citas/confirmar` (POST)
- `mostrarConstancia()` — `citas/constancia` (GET)

## ControlGestionarEstadosCita — CU15 Gestionar Estados de Cita (RF15).
- `listarAgenda()` — `citas/agenda` (GET)
- `confirmarCita()` — `citas/estado/confirmar` (POST)
- `marcarAtendida()` — `citas/estado/atender` (POST)
- `marcarInasistencia()` — `citas/estado/inasistencia` (POST)

## ControlReprogramarCancelarCita — CU16 Reprogramar y cancelar cita.
- `listarCitas()` — `citas/proximas` (GET)
- `mostrarCita()` — `citas/cambiar` (GET)
- `reprogramarCita()` — `citas/reprogramar` (POST)
- `cancelarCita()` — `citas/cancelar` (POST)

## ControlVerificarDisponibilidad — CU13 Verificar Disponibilidad Horaria (RF05, RF07).   Responsable: Jackeline
- `mostrarFiltros()` — `disponibilidad` (GET)
- `consultarCupos()` — llamado por otro control («include»)
- `verificarCupoLibre()` — llamado por otro control («include»)
- `validarFecha()` — llamado por otro control («include»)

## ControlAsistenteVirtual — CU25 Asistente virtual (reglas, sin API externa).
- `mostrarChat()` — `asistente` (GET)
- `enviarMensaje()` — `asistente/enviar` (POST)
- `cerrarConversacion()` — `asistente/cerrar` (POST)

## ControlGestionarBaseConocimiento — CU26 Base de conocimiento.
- `listarPreguntas()` — `conocimiento` (GET)
- `mostrarFormulario()` — `conocimiento/formulario` (GET)
- `guardarPregunta()` — `conocimiento/guardar` (POST)
- `cambiarEstado()` — `conocimiento/estado` (POST)

## ControlGestionarNotificaciones — CU24 Notificaciones y recordatorios.
- `listarNotificaciones()` — `notificaciones` (GET)
- `marcarLeida()` — `notificaciones/leida` (POST)
- `enviarRecordatorios()` — `notificaciones/recordatorios` (POST)
- `ejecutarTareaProgramada()` — `notificaciones/tarea` (GET)
- `notificarCita()` — llamado por otro control («include»)
- `notificarEventos()` — llamado por otro control («include»)

## ControlDarBajaMedico — CU10 Dar de Baja a un Médico.   Ciclo 2 · P3 · Responsable: Jackeline
- `mostrarBaja()` — `bajas` (GET)
- `confirmarBaja()` — `bajas/confirmar` (POST)
- `reactivarMedico()` — `bajas/reactivar` (POST)

## ControlGestionarEspecialidades — CU08 Gestionar Especialidades.
- `listarEspecialidades()` — `especialidades` (GET)
- `mostrarFormulario()` — `especialidades/formulario` (GET)
- `guardarEspecialidad()` — `especialidades/guardar` (POST)
- `cambiarEstado()` — `especialidades/estado` (POST)

## ControlGestionarMedicos — CU09 Gestionar Médicos (RF03).   Responsable: Erika
- `listarMedicos()` — `medicos` (GET)
- `mostrarFormulario()` — `medicos/formulario` (GET)
- `guardarMedico()` — `medicos/guardar` (POST)

## ControlGestionarResultados — CU23 Gestionar Resultados de Laboratorio.   Ciclo 2 · P8 · Responsable: Jackeline
- `listarResultados()` — `laboratorio` (GET)
- `mostrarSolicitud()` — `laboratorio/solicitar` (GET)
- `solicitarExamen()` — `laboratorio/solicitar/guardar` (POST)
- `marcarEnProceso()` — `laboratorio/en-proceso` (POST)
- `mostrarFormulario()` — `laboratorio/formulario` (GET)
- `registrarResultado()` — `laboratorio/guardar` (POST)
- `marcarEntregado()` — `laboratorio/entregar` (POST)
- `cancelarSolicitud()` — `laboratorio/cancelar` (POST)
- `descargarArchivo()` — `laboratorio/archivo` (GET)

## ControlConsultarHistorial — CU07 Consultar Historial del Paciente.   Ciclo 2 · P8 · Responsable: Jackeline
- `mostrarHistorial()` — `historial` (GET)

## ControlGestionarFichaClinica — CU21 Gestionar Ficha Clínica Básica.   Ciclo 2 · P8 · Responsable: Jackeline
- `mostrarFicha()` — `ficha` (GET)
- `guardarFicha()` — `ficha/guardar` (POST)

## ControlGestionarGrupoFamiliar — CU22 Gestionar Grupo Familiar.   Ciclo 2 · P2 · Responsable: Jackeline
- `mostrarGrupo()` — `grupo-familiar` (GET)
- `agregarFamiliar()` — `grupo-familiar/agregar` (POST)
- `quitarFamiliar()` — `grupo-familiar/quitar` (POST)
- `mostrarOtraPersona()` — `citas/otra-persona` (GET)
- `registrarOtraPersona()` — `citas/otra-persona/guardar` (POST)

## ControlGestionarPacientes — CU05 Gestionar Pacientes (RF02).   Responsable: Erika
- `listarPacientes()` — `pacientes` (GET)
- `mostrarFormulario()` — `pacientes/formulario` (GET)
- `guardarPaciente()` — `pacientes/guardar` (POST)
- `cambiarEstado()` — `pacientes/estado` (POST)

## ControlValidarRestricciones — CU06 Validar Edad y Restricciones («include» de CU14, junto con CU13).
- `obtenerEspecialidadesPermitidas()` — llamado por otro control («include»)
- `consultarRestricciones()` — `restricciones` (GET)

## ControlConsultarPagos — CU20 Consultar Pagos y Comprobantes.   Ciclo 2 · P7 · Responsable: Jackeline
- `listarHistorial()` — `comprobantes` (GET)
- `mostrarComprobante()` — `comprobantes/ver` (GET)

## ControlProcesarPago — CU19 Procesar Pago en Línea (Stripe, modo prueba). «extend» de CU14 («Pagar ahora»).
- `listarPagos()` — `pagos` (GET)
- `mostrarDetalle()` — `pagos/detalle` (GET)
- `iniciarPago()` — `pagos/iniciar` (POST)
- `retornoPago()` — `pagos/retorno` (GET)
- `pagoCancelado()` — `pagos/cancelado` (GET)
- `recibirNotificacion()` — `pagos/webhook` (POST)
- `registrarPagoManual()` — `pagos/registrar` (POST)
- `confirmarPorSeguro()` — `pagos/seguro` (POST)
- `descargarComprobante()` — `pagos/comprobante` (GET)

## ControlGenerarReportes — CU18 Reportes operativos (RF13).
- `mostrarReportes()` — `reportes` (GET)
- `exportarReporte()` — `reportes/exportar` (GET)

## ControlGestionarBitacora — consulta de la bitácora de auditoría (solo lectura).
- `consultarBitacora()` — `bitacora` (GET)

## ControlGestionarCuentas — CU02 Gestionar Cuentas de Usuario.   Responsable: Erika
- `listarCuentas()` — `cuentas` (GET)
- `mostrarFormulario()` — `cuentas/formulario` (GET)
- `guardarCuenta()` — `cuentas/guardar` (POST)
- `cambiarEstado()` — `cuentas/estado` (POST)

## ControlGestionarRoles — CU03 Gestionar Roles y Permisos (RBAC).
- `listarRoles()` — `roles` (GET)
- `guardarPermisos()` — `roles/guardar` (POST)

## ControlIniciarSesion — CU01 Iniciar Sesión.
- `mostrarFormulario()` — `sesion/formulario` (GET)
- `iniciarSesion()` — `sesion/iniciar` (POST)
- `mostrarCrearCuenta()` — `sesion/registro` (GET)
- `crearCuenta()` — `sesion/registro/guardar` (POST)
- `cerrarSesion()` — `sesion/cerrar` (POST)
- `mostrarPanel()` — `panel` (GET)
- `mostrarRecuperacion()` — `sesion/recuperar` (GET)
- `solicitarRecuperacion()` — `sesion/recuperar/enviar` (POST)
- `mostrarRestablecer()` — `sesion/restablecer` (GET)
- `restablecerContrasena()` — `sesion/restablecer/guardar` (POST)
- `mostrarCambioContrasena()` — `sesion/cambiar` (GET)
- `cambiarContrasena()` — `sesion/cambiar/guardar` (POST)

## ControlPerfilPaciente — Mi perfil (extensión de CU01 y CU24).
- `mostrarPerfil()` — `perfil` (GET)
- `guardarPerfil()` — `perfil/guardar` (POST)
