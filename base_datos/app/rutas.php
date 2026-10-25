<?php
/**
 * Rutas del sistema:  ?r=ruta  =>  [Control, operación, método HTTP]
 * Cada ruta corresponde a un mensaje IU -> Control del diagrama de comunicación.
 *
 * CICLO #1 — 8 casos de uso. REGLA DEL EQUIPO: cada CU tiene su bloque;
 * modifica SOLO el bloque de tus CU. Así dos ramas nunca tocan las mismas líneas.
 */
use App\Controles\Agendas\ControlConfigurarJornada;
use App\Controles\Citas\ControlAgendarCita;
use App\Controles\Citas\ControlVerificarDisponibilidad;
use App\Controles\Especialidades\ControlGestionarEspecialidades;
use App\Controles\Especialidades\ControlGestionarMedicos;
use App\Controles\Pacientes\ControlGestionarPacientes;
use App\Controles\Pacientes\ControlValidarRestricciones;
use App\Controles\Pagos\ControlProcesarPago;
use App\Controles\Seguridad\ControlGestionarBitacora;
use App\Controles\Seguridad\ControlGestionarCuentas;
use App\Controles\Seguridad\ControlGestionarRoles;
use App\Controles\Seguridad\ControlIniciarSesion;

return [
    // ===================== ERIKA =====================
    // CU01 Iniciar Sesión
    'sesion/formulario'          => [ControlIniciarSesion::class, 'mostrarFormulario', 'GET'],
    'sesion/iniciar'             => [ControlIniciarSesion::class, 'iniciarSesion', 'POST'],
    'sesion/cerrar'              => [ControlIniciarSesion::class, 'cerrarSesion', 'POST'],
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
    'pagos/comprobante'          => [ControlProcesarPago::class, 'descargarComprobante', 'GET'],
];
