<?php
/**
 * Rutas del sistema:  ?r=ruta  =>  [Control, operación, método HTTP]
 * Cada ruta corresponde a un mensaje IU -> Control del diagrama de comunicación.
 *
 * CICLO #1 — 8 casos de uso. REGLA DEL EQUIPO: cada CU tiene su bloque;
 * modifica SOLO el bloque de tus CU. Así dos ramas nunca tocan las mismas líneas.
 */
use App\Controles\Agendas\ControlConfigurarJornada;
use App\Controles\Agendas\ControlGestionarBloqueos;
use App\Controles\Comunicacion\ControlAsistenteVirtual;
use App\Controles\Comunicacion\ControlGestionarBaseConocimiento;
use App\Controles\Comunicacion\ControlGestionarNotificaciones;
use App\Controles\Citas\ControlAgendarCita;
use App\Controles\Citas\ControlGestionarEstadosCita;
use App\Controles\Citas\ControlReprogramarCancelarCita;
use App\Controles\Citas\ControlVerificarDisponibilidad;
use App\Controles\Especialidades\ControlDarBajaMedico;
use App\Controles\Especialidades\ControlGestionarEspecialidades;
use App\Controles\Especialidades\ControlGestionarMedicos;
use App\Controles\Laboratorio\ControlGestionarResultados;
use App\Controles\Pacientes\ControlConsultarHistorial;
use App\Controles\Pacientes\ControlGestionarFichaClinica;
use App\Controles\Pacientes\ControlGestionarGrupoFamiliar;
use App\Controles\Pacientes\ControlGestionarPacientes;
use App\Controles\Pacientes\ControlValidarRestricciones;
use App\Controles\Pagos\ControlConsultarPagos;
use App\Controles\Pagos\ControlProcesarPago;
use App\Controles\Reportes\ControlGenerarReportes;
use App\Controles\Seguridad\ControlGestionarBitacora;
use App\Controles\Seguridad\ControlGestionarCuentas;
use App\Controles\Seguridad\ControlGestionarRoles;
use App\Controles\Seguridad\ControlIniciarSesion;
use App\Controles\Seguridad\ControlPerfilPaciente;

return [
    // ===================== ERIKA =====================
    // CU01 Iniciar Sesión
    'sesion/formulario'          => [ControlIniciarSesion::class, 'mostrarFormulario', 'GET'],
    'sesion/iniciar'             => [ControlIniciarSesion::class, 'iniciarSesion', 'POST'],
    'sesion/cerrar'              => [ControlIniciarSesion::class, 'cerrarSesion', 'POST'],
    'perfil'                     => [ControlPerfilPaciente::class, 'mostrarPerfil', 'GET'],
    'perfil/guardar'             => [ControlPerfilPaciente::class, 'guardarPerfil', 'POST'],
    'panel'                      => [ControlIniciarSesion::class, 'mostrarPanel', 'GET'],
    'sesion/recuperar'           => [ControlIniciarSesion::class, 'mostrarRecuperacion', 'GET'],
    'sesion/recuperar/enviar'    => [ControlIniciarSesion::class, 'solicitarRecuperacion', 'POST'],
    'sesion/restablecer'         => [ControlIniciarSesion::class, 'mostrarRestablecer', 'GET'],
    'sesion/restablecer/guardar' => [ControlIniciarSesion::class, 'restablecerContrasena', 'POST'],
    'sesion/cambiar'             => [ControlIniciarSesion::class, 'mostrarCambioContrasena', 'GET'],
    'sesion/cambiar/guardar'     => [ControlIniciarSesion::class, 'cambiarContrasena', 'POST'],
    // CU02 Gestionar Cuentas de Usuario
    'cuentas'                    => [ControlGestionarCuentas::class, 'listarCuentas', 'GET'],
    'cuentas/formulario'         => [ControlGestionarCuentas::class, 'mostrarFormulario', 'GET'],
    'cuentas/guardar'            => [ControlGestionarCuentas::class, 'guardarCuenta', 'POST'],
    'cuentas/estado'             => [ControlGestionarCuentas::class, 'cambiarEstado', 'POST'],
    // Bitácora de auditoría (solo lectura)
    'bitacora'                   => [ControlGestionarBitacora::class, 'consultarBitacora', 'GET'],
    // CU05 Gestionar Pacientes
    'pacientes'                  => [ControlGestionarPacientes::class, 'listarPacientes', 'GET'],
    'pacientes/formulario'       => [ControlGestionarPacientes::class, 'mostrarFormulario', 'GET'],
    'pacientes/guardar'          => [ControlGestionarPacientes::class, 'guardarPaciente', 'POST'],
    'pacientes/estado'           => [ControlGestionarPacientes::class, 'cambiarEstado', 'POST'],
    // CU09 Gestionar Médicos
    'medicos'                    => [ControlGestionarMedicos::class, 'listarMedicos', 'GET'],
    'medicos/formulario'         => [ControlGestionarMedicos::class, 'mostrarFormulario', 'GET'],
    'medicos/guardar'            => [ControlGestionarMedicos::class, 'guardarMedico', 'POST'],

    // ===================== JACKELINE =====================
    // CU08 Gestionar Especialidades
    'especialidades'             => [ControlGestionarEspecialidades::class, 'listarEspecialidades', 'GET'],
    'especialidades/formulario'  => [ControlGestionarEspecialidades::class, 'mostrarFormulario', 'GET'],
    'especialidades/guardar'     => [ControlGestionarEspecialidades::class, 'guardarEspecialidad', 'POST'],
    'especialidades/estado'      => [ControlGestionarEspecialidades::class, 'cambiarEstado', 'POST'],
    // CU03 Gestionar Roles y Permisos
    'roles'                      => [ControlGestionarRoles::class, 'listarRoles', 'GET'],
    'roles/guardar'              => [ControlGestionarRoles::class, 'guardarPermisos', 'POST'],
    // CU11 Configurar Jornada de Atención
    'jornadas'                   => [ControlConfigurarJornada::class, 'mostrarJornada', 'GET'],
    'jornadas/guardar'           => [ControlConfigurarJornada::class, 'guardarJornada', 'POST'],
    // CU13 Verificar Disponibilidad Horaria
    'disponibilidad'             => [ControlVerificarDisponibilidad::class, 'mostrarFiltros', 'GET'],
    // CU14 Agendar Cita Médica (incluye CU06 y CU13)
    'citas/nueva'                => [ControlAgendarCita::class, 'mostrarFormulario', 'GET'],
    'citas/para-mi'              => [ControlAgendarCita::class, 'seleccionarParaMi', 'POST'],   // abre su expediente si aún no lo tiene
    'citas/confirmar'            => [ControlAgendarCita::class, 'confirmarCita', 'POST'],
    'citas/constancia'           => [ControlAgendarCita::class, 'mostrarConstancia', 'GET'],
    // CU06 Validar Edad y Restricciones (pantalla de consulta; también la usa CU14 como «include»)
    'restricciones'              => [ControlValidarRestricciones::class, 'consultarRestricciones', 'GET'],
    // CU19 Procesar Pago en Línea (Stripe, modo prueba)
    'pagos'                      => [ControlProcesarPago::class, 'listarPagos', 'GET'],
    'pagos/detalle'              => [ControlProcesarPago::class, 'mostrarDetalle', 'GET'],
    'pagos/iniciar'              => [ControlProcesarPago::class, 'iniciarPago', 'POST'],
    'pagos/retorno'              => [ControlProcesarPago::class, 'retornoPago', 'GET'],
    'pagos/cancelado'            => [ControlProcesarPago::class, 'pagoCancelado', 'GET'],
    'pagos/webhook'              => [ControlProcesarPago::class, 'recibirNotificacion', 'POST', 'sin_csrf'],
    'pagos/seguro'               => [ControlProcesarPago::class, 'confirmarPorSeguro', 'POST'],
'pagos/registrar'            => [ControlProcesarPago::class, 'registrarPagoManual', 'POST'],
    'pagos/comprobante'          => [ControlProcesarPago::class, 'descargarComprobante', 'GET'],

    // ===================== CICLO 2 — GRUPO 1 =====================
    // CU15 Gestionar Estados de Cita (y CU17 Registrar Inasistencia, «extend»)
    'citas/agenda'               => [ControlGestionarEstadosCita::class, 'listarAgenda', 'GET'],
    'citas/estado/confirmar'     => [ControlGestionarEstadosCita::class, 'confirmarCita', 'POST'],
    'citas/estado/atender'       => [ControlGestionarEstadosCita::class, 'marcarAtendida', 'POST'],
    'citas/estado/inasistencia'  => [ControlGestionarEstadosCita::class, 'marcarInasistencia', 'POST'],
    // CU16 Reprogramar y cancelar cita
    'citas/proximas'             => [ControlReprogramarCancelarCita::class, 'listarCitas', 'GET'],
    'citas/cambiar'              => [ControlReprogramarCancelarCita::class, 'mostrarCita', 'GET'],
    'citas/reprogramar'          => [ControlReprogramarCancelarCita::class, 'reprogramarCita', 'POST'],
    'citas/cancelar'             => [ControlReprogramarCancelarCita::class, 'cancelarCita', 'POST'],
    // CU18 Reportes operativos
    'reportes'                   => [ControlGenerarReportes::class, 'mostrarReportes', 'GET'],
    'reportes/exportar'          => [ControlGenerarReportes::class, 'exportarReporte', 'GET'],
    // CU24 Notificaciones y recordatorios
    'notificaciones'             => [ControlGestionarNotificaciones::class, 'listarNotificaciones', 'GET'],
    'notificaciones/leida'       => [ControlGestionarNotificaciones::class, 'marcarLeida', 'POST'],
    'notificaciones/recordatorios' => [ControlGestionarNotificaciones::class, 'enviarRecordatorios', 'POST'],
    'notificaciones/tarea'       => [ControlGestionarNotificaciones::class, 'ejecutarTareaProgramada', 'GET'],   // tarea diaria, sin sesión
    // CU25 Asistente virtual (también para visitantes sin sesión)
    'asistente'                  => [ControlAsistenteVirtual::class, 'mostrarChat', 'GET'],
    'asistente/enviar'           => [ControlAsistenteVirtual::class, 'enviarMensaje', 'POST'],
    'asistente/cerrar'           => [ControlAsistenteVirtual::class, 'cerrarConversacion', 'POST'],
    // CU26 Base de conocimiento
    'conocimiento'               => [ControlGestionarBaseConocimiento::class, 'listarPreguntas', 'GET'],
    'conocimiento/formulario'    => [ControlGestionarBaseConocimiento::class, 'mostrarFormulario', 'GET'],
    'conocimiento/guardar'       => [ControlGestionarBaseConocimiento::class, 'guardarPregunta', 'POST'],
    'conocimiento/estado'        => [ControlGestionarBaseConocimiento::class, 'cambiarEstado', 'POST'],
    // ===================== CICLO 2 — GRUPO 2 (compañera) =====================
    // CU07 Consultar Historial del Paciente
    'historial'                  => [ControlConsultarHistorial::class, 'mostrarHistorial', 'GET'],
    // CU10 Dar de Baja a un Médico
    'bajas'                      => [ControlDarBajaMedico::class, 'mostrarBaja', 'GET'],
    'bajas/confirmar'            => [ControlDarBajaMedico::class, 'confirmarBaja', 'POST'],
    'bajas/reactivar'            => [ControlDarBajaMedico::class, 'reactivarMedico', 'POST'],
    // CU12 Gestionar Bloqueos de Agenda
    'bloqueos'                   => [ControlGestionarBloqueos::class, 'listarBloqueos', 'GET'],
    'bloqueos/formulario'        => [ControlGestionarBloqueos::class, 'mostrarFormulario', 'GET'],
    'bloqueos/guardar'           => [ControlGestionarBloqueos::class, 'guardarBloqueo', 'POST'],
    'bloqueos/quitar'            => [ControlGestionarBloqueos::class, 'quitarBloqueo', 'POST'],
    // CU20 Consultar Pagos y Comprobantes
    'comprobantes'               => [ControlConsultarPagos::class, 'listarHistorial', 'GET'],
    'comprobantes/ver'           => [ControlConsultarPagos::class, 'mostrarComprobante', 'GET'],
    // CU21 Gestionar Ficha Clínica Básica
    'ficha'                      => [ControlGestionarFichaClinica::class, 'mostrarFicha', 'GET'],
    'ficha/guardar'              => [ControlGestionarFichaClinica::class, 'guardarFicha', 'POST'],
    // CU22 Gestionar Grupo Familiar
    'grupo-familiar'             => [ControlGestionarGrupoFamiliar::class, 'mostrarGrupo', 'GET'],
    'grupo-familiar/agregar'     => [ControlGestionarGrupoFamiliar::class, 'agregarFamiliar', 'POST'],
    'grupo-familiar/quitar'      => [ControlGestionarGrupoFamiliar::class, 'quitarFamiliar', 'POST'],
    'citas/otra-persona'         => [ControlGestionarGrupoFamiliar::class, 'mostrarOtraPersona', 'GET'],      // CU14 + CU22
    'citas/otra-persona/guardar' => [ControlGestionarGrupoFamiliar::class, 'registrarOtraPersona', 'POST'],
    // CU23 Gestionar Resultados de Laboratorio
    'laboratorio'                => [ControlGestionarResultados::class, 'listarResultados', 'GET'],
    'laboratorio/solicitar'      => [ControlGestionarResultados::class, 'mostrarSolicitud', 'GET'],
    'laboratorio/solicitar/guardar' => [ControlGestionarResultados::class, 'solicitarExamen', 'POST'],
    'laboratorio/en-proceso'     => [ControlGestionarResultados::class, 'marcarEnProceso', 'POST'],
    'laboratorio/formulario'     => [ControlGestionarResultados::class, 'mostrarFormulario', 'GET'],
    'laboratorio/guardar'        => [ControlGestionarResultados::class, 'registrarResultado', 'POST'],
    'laboratorio/entregar'       => [ControlGestionarResultados::class, 'marcarEntregado', 'POST'],
    'laboratorio/cancelar'       => [ControlGestionarResultados::class, 'cancelarSolicitud', 'POST'],
    'laboratorio/archivo'        => [ControlGestionarResultados::class, 'descargarArchivo', 'GET'],
    // Paciente nuevo: registro en línea (extensión de CU01 / CU05)
    'sesion/registro'            => [ControlIniciarSesion::class, 'mostrarCrearCuenta', 'GET'],
    'sesion/registro/guardar'    => [ControlIniciarSesion::class, 'crearCuenta', 'POST'],
];
