<?php
/**
 * Mapa de CICLOS y CASOS DE USO — según la documentación (4.2 Priorizar casos de uso y 4.5.1 Paquetes).
 * Única fuente para: el menú lateral, el panel «Inicio» y la etiqueta del CU que se muestra en cada pantalla.
 *
 * Cada ítem enlaza el diagrama con el código:
 *   'cu'     => código del caso de uso (CU01, CU02...).
 *   'ruta'   => ?r=ruta de entrada (ver app/rutas.php; ahí está el «control» de ese CU).
 *   'rutas'  => prefijos de ruta del mismo CU (para marcar el menú como activo).
 *   'modulo' / 'accion' => permiso RBAC (C consultar, R registrar, M modificar, A anular).
 *                          Si el rol del usuario no lo tiene, el ítem aparece BLOQUEADO (no oculto).
 *   'listo'  => true cuando el CU está implementado y revisado.
 *
 * CICLO 1: 12 casos de uso (CU01 a CU06, CU08, CU09, CU11, CU13, CU14 y CU19).
 * CICLO 2: 14 casos de uso, bloqueado ('abierto' => false). Para abrir uno: poner su 'ruta',
 *          'listo' => true y, al empezar el ciclo, 'abierto' => true.
 */
return [
    1 => [
        'nombre' => 'Ciclo 1', 'abierto' => true,
        'titulo' => 'Base del agendamiento: seguridad, pacientes, catálogo, jornadas, citas y pago',
        'paquetes' => [
            ['id' => 'P1', 'titulo' => 'Seguridad, acceso y auditoría', 'items' => [
                ['cu' => 'CU01', 'texto' => 'Iniciar sesión', 'desc' => 'Ingreso seguro, bloqueo por intentos fallidos, recuperar y cambiar contraseña.',
                 'ruta' => 'sesion/cambiar', 'rutas' => ['sesion/cambiar'], 'modulo' => null, 'accion' => null, 'icono' => 'llave', 'listo' => true],
                ['cu' => 'CU02', 'texto' => 'Cuentas de usuario', 'desc' => 'Altas, modificación y suspensión de cuentas con su rol.',
                 'ruta' => 'cuentas', 'rutas' => ['cuentas'], 'modulo' => 'SEGURIDAD', 'accion' => 'C', 'icono' => 'usuarios', 'listo' => true],
                ['cu' => 'CU03', 'texto' => 'Roles y permisos', 'desc' => 'Matriz de permisos por rol y módulo (RBAC).',
                 'ruta' => 'roles', 'rutas' => ['roles'], 'modulo' => 'SEGURIDAD', 'accion' => 'M', 'icono' => 'escudo', 'listo' => true],
                ['cu' => 'CU04', 'texto' => 'Bitácora de auditoría', 'desc' => 'Eventos sensibles con usuario, fecha e IP de origen. Solo lectura.',
                 'ruta' => 'bitacora', 'rutas' => ['bitacora'], 'modulo' => 'SEGURIDAD', 'accion' => 'C', 'icono' => 'libro', 'listo' => true],
            ]],
            ['id' => 'P2', 'titulo' => 'Pacientes', 'items' => [
                ['cu' => 'CU05', 'texto' => 'Pacientes', 'desc' => 'Registro, búsqueda y estado de pacientes con su expediente.',
                 'ruta' => 'pacientes', 'rutas' => ['pacientes'], 'modulo' => 'PACIENTES', 'accion' => 'C', 'icono' => 'paciente', 'listo' => true],
                ['cu' => 'CU06', 'texto' => 'Edad y restricciones', 'desc' => 'Especialidades permitidas según la edad y el sexo del paciente.',
                 'ruta' => 'restricciones', 'rutas' => ['restricciones'], 'modulo' => 'CITAS', 'accion' => 'C', 'icono' => 'corazon', 'listo' => true],
            ]],
            ['id' => 'P3', 'titulo' => 'Especialidades y médicos', 'items' => [
                ['cu' => 'CU08', 'texto' => 'Especialidades', 'desc' => 'Catálogo de especialidades, costo de consulta y rango de edad.',
                 'ruta' => 'especialidades', 'rutas' => ['especialidades'], 'modulo' => 'ESPECIALIDADES_MEDICOS', 'accion' => 'C', 'icono' => 'corazon', 'listo' => true],
                ['cu' => 'CU09', 'texto' => 'Médicos', 'desc' => 'Médicos, matrícula y las especialidades que atienden.',
                 'ruta' => 'medicos', 'rutas' => ['medicos'], 'modulo' => 'ESPECIALIDADES_MEDICOS', 'accion' => 'C', 'icono' => 'medico', 'listo' => true],
            ]],
            ['id' => 'P4', 'titulo' => 'Agendas y horarios', 'items' => [
                ['cu' => 'CU11', 'texto' => 'Jornada de atención', 'desc' => 'Días y horarios de atención de cada médico.',
                 'ruta' => 'jornadas', 'rutas' => ['jornadas'], 'modulo' => 'AGENDAS', 'accion' => 'C', 'icono' => 'reloj', 'listo' => true],
            ]],
            ['id' => 'P5', 'titulo' => 'Citas médicas', 'items' => [
                ['cu' => 'CU13', 'texto' => 'Disponibilidad horaria', 'desc' => 'Cupos libres por especialidad, médico y fecha.',
                 'ruta' => 'disponibilidad', 'rutas' => ['disponibilidad'], 'modulo' => 'CITAS', 'accion' => 'C', 'icono' => 'calendario', 'listo' => true],
                ['cu' => 'CU14', 'texto' => 'Agendar cita', 'desc' => 'Reserva de cita; incluye CU06 y CU13 y permite pagar ahora (CU19).',
                 'ruta' => 'citas/nueva', 'rutas' => ['citas'], 'modulo' => 'CITAS', 'accion' => 'R', 'icono' => 'calendario-ok', 'listo' => true],
            ]],
            ['id' => 'P7', 'titulo' => 'Pagos y transacciones', 'items' => [
                ['cu' => 'CU19', 'texto' => 'Pago en línea (Stripe)', 'desc' => 'Cobro de la cita con tarjeta de prueba, confirmación y comprobante PDF.',
                 'ruta' => 'pagos', 'rutas' => ['pagos'], 'modulo' => 'PAGOS', 'accion' => 'C', 'icono' => 'tarjeta', 'listo' => true],
            ]],
        ],
    ],

    2 => [
        'nombre' => 'Ciclo 2', 'abierto' => false,
        'titulo' => 'Gestión completa de la cita, reportes, historial, notificaciones y asistente virtual',
        'paquetes' => [
            ['id' => 'P2', 'titulo' => 'Pacientes', 'items' => [
                ['cu' => 'CU22', 'texto' => 'Grupo familiar', 'desc' => 'Vincula a un responsable con sus dependientes.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'PACIENTES', 'accion' => 'C', 'icono' => 'usuarios', 'listo' => false],
            ]],
            ['id' => 'P3', 'titulo' => 'Especialidades y médicos', 'items' => [
                ['cu' => 'CU10', 'texto' => 'Dar de baja a médico', 'desc' => 'Baja lógica que conserva el historial.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'ESPECIALIDADES_MEDICOS', 'accion' => 'C', 'icono' => 'medico', 'listo' => false],
            ]],
            ['id' => 'P4', 'titulo' => 'Agendas y horarios', 'items' => [
                ['cu' => 'CU12', 'texto' => 'Bloqueos de agenda', 'desc' => 'Ausencias y vacaciones que quitan cupos.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'AGENDAS', 'accion' => 'C', 'icono' => 'reloj', 'listo' => false],
            ]],
            ['id' => 'P5', 'titulo' => 'Citas médicas', 'items' => [
                ['cu' => 'CU15', 'texto' => 'Estados de cita', 'desc' => 'Programada, confirmada, atendida, cancelada.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'CITAS', 'accion' => 'C', 'icono' => 'calendario-ok', 'listo' => false],
                ['cu' => 'CU16', 'texto' => 'Reprogramar o cancelar', 'desc' => 'Libera el cupo y propone nuevo horario.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'CITAS', 'accion' => 'C', 'icono' => 'calendario', 'listo' => false],
                ['cu' => 'CU17', 'texto' => 'Registrar inasistencia', 'desc' => 'Alimenta los indicadores de ausentismo.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'CITAS', 'accion' => 'C', 'icono' => 'calendario', 'listo' => false],
            ]],
            ['id' => 'P6', 'titulo' => 'Reportes', 'items' => [
                ['cu' => 'CU18', 'texto' => 'Reportes operativos', 'desc' => 'Productividad por médico e ingresos diarios.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'REPORTES', 'accion' => 'C', 'icono' => 'grafico', 'listo' => false],
            ]],
            ['id' => 'P7', 'titulo' => 'Pagos y transacciones', 'items' => [
                ['cu' => 'CU20', 'texto' => 'Consultar pagos y comprobantes', 'desc' => 'Historial de pagos y descarga de comprobantes.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'PAGOS', 'accion' => 'C', 'icono' => 'tarjeta', 'listo' => false],
            ]],
            ['id' => 'P8', 'titulo' => 'Historial clínico y laboratorio', 'items' => [
                ['cu' => 'CU07', 'texto' => 'Historial del paciente', 'desc' => 'Citas y atenciones anteriores del paciente.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'PACIENTES', 'accion' => 'C', 'icono' => 'libro', 'listo' => false],
                ['cu' => 'CU21', 'texto' => 'Ficha clínica básica', 'desc' => 'Antecedentes, alergias y datos clínicos.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'PACIENTES', 'accion' => 'C', 'icono' => 'paciente', 'listo' => false],
                ['cu' => 'CU23', 'texto' => 'Resultados de laboratorio', 'desc' => 'Carga y descarga de resultados de exámenes.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'EXAMENES', 'accion' => 'C', 'icono' => 'frasco', 'listo' => false],
            ]],
            ['id' => 'P9', 'titulo' => 'Notificaciones y recordatorios', 'items' => [
                ['cu' => 'CU24', 'texto' => 'Notificaciones y recordatorios', 'desc' => 'Avisos por correo de citas, pagos y resultados.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'COMUNICACION', 'accion' => 'C', 'icono' => 'chat', 'listo' => false],
            ]],
            ['id' => 'P10', 'titulo' => 'Asistente virtual', 'items' => [
                ['cu' => 'CU25', 'texto' => 'Asistente virtual', 'desc' => 'Chatbot para consultar y reservar citas.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'COMUNICACION', 'accion' => 'C', 'icono' => 'chat', 'listo' => false],
                ['cu' => 'CU26', 'texto' => 'Base de conocimiento', 'desc' => 'Preguntas frecuentes que alimentan al asistente.',
                 'ruta' => null, 'rutas' => [], 'modulo' => 'COMUNICACION', 'accion' => 'C', 'icono' => 'libro', 'listo' => false],
            ]],
        ],
    ],
];
