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
use App\Controles\Especialidades\ControlDarBajaMedico;
use App\Controles\Laboratorio\ControlGestionarResultados;
use App\Controles\Pacientes\ControlConsultarHistorial;
use App\Controles\Pacientes\ControlGestionarFichaClinica;
use App\Controles\Pacientes\ControlGestionarGrupoFamiliar;
use App\Controles\Pagos\ControlConsultarPagos;
use App\Controles\Citas\ControlAgendarCita;
use App\Controles\Citas\ControlVerificarDisponibilidad;
use App\Controles\Especialidades\ControlGestionarEspecialidades;
use App\Controles\Especialidades\ControlGestionarMedicos;
use App\Controles\Pacientes\ControlGestionarPacientes;
use App\Controles\Seguridad\ControlGestionarCuentas;
use App\Controles\Seguridad\ControlIniciarSesion;

return [
    // ===================== ERIKA =====================
    // CU01 Iniciar Sesión
    'sesion/formulario'          => [ControlIniciarSesion::class, 'mostrarFormulario', 'GET'],
    'sesion/iniciar'             => [ControlIniciarSesion::class, 'iniciarSesion', 'POST'],
    'sesion/cerrar'              => [ControlIniciarSesion::class, 'cerrarSesion', 'POST'],
    'panel'                      => [ControlIniciarSesion::class, 'mostrarPanel', 'GET'],
    // CU02 Gestionar Cuentas de Usuario
    'cuentas'                    => [ControlGestionarCuentas::class, 'listarCuentas', 'GET'],
    'cuentas/formulario'         => [ControlGestionarCuentas::class, 'mostrarFormulario', 'GET'],
    'cuentas/guardar'            => [ControlGestionarCuentas::class, 'guardarCuenta', 'POST'],
    'cuentas/estado'             => [ControlGestionarCuentas::class, 'cambiarEstado', 'POST'],
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
    // CU11 Configurar Jornada de Atención
    'jornadas'                   => [ControlConfigurarJornada::class, 'mostrarJornada', 'GET'],
    'jornadas/guardar'           => [ControlConfigurarJornada::class, 'guardarJornada', 'POST'],
    // CU13 Verificar Disponibilidad Horaria
    'disponibilidad'             => [ControlVerificarDisponibilidad::class, 'mostrarFiltros', 'GET'],
    // CU14 Agendar Cita Médica (incluye CU06 y CU13)
    'citas/nueva'                => [ControlAgendarCita::class, 'mostrarFormulario', 'GET'],
    'citas/confirmar'            => [ControlAgendarCita::class, 'confirmarCita', 'POST'],
    'citas/constancia'           => [ControlAgendarCita::class, 'mostrarConstancia', 'GET'],

    // ===================== JACKELINE · CICLO #2 =====================
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
];
