<?php
/**
 * Menú lateral — CICLO #1 (8 casos de uso). Una opción se muestra solo si:
 *   - 'listo' => true   (el CU ya está implementado y revisado), y
 *   - el rol tiene el permiso modulo/accion (RBAC).
 * REGLA DEL EQUIPO: solo cambias 'listo' EN TU LÍNEA. No reordenes ni agregues líneas en medio.
 */
return [
    ['texto' => 'Inicio',              'ruta' => 'panel',          'modulo' => null,                     'accion' => null, 'listo' => true], // CU01 · Erika

    ['grupo' => 'Citas'],
    ['texto' => 'Agendar cita',        'ruta' => 'citas/nueva',    'modulo' => 'CITAS',                  'accion' => 'R', 'listo' => true], // CU14 · Jackeline
    ['texto' => 'Disponibilidad',      'ruta' => 'disponibilidad', 'modulo' => 'CITAS',                  'accion' => 'C', 'listo' => true], // CU13 · Jackeline

    ['grupo' => 'Pacientes'],
    ['texto' => 'Pacientes',           'ruta' => 'pacientes',      'modulo' => 'PACIENTES',              'accion' => 'C', 'listo' => true], // CU05 · Erika

    ['grupo' => 'Catálogo médico'],
    ['texto' => 'Especialidades',      'ruta' => 'especialidades', 'modulo' => 'ESPECIALIDADES_MEDICOS', 'accion' => 'C', 'listo' => true], // CU08 · Jackeline
    ['texto' => 'Médicos',             'ruta' => 'medicos',        'modulo' => 'ESPECIALIDADES_MEDICOS', 'accion' => 'C', 'listo' => true], // CU09 · Erika
    ['texto' => 'Jornadas',            'ruta' => 'jornadas',       'modulo' => 'AGENDAS',                'accion' => 'C', 'listo' => true], // CU11 · Jackeline

    ['grupo' => 'Administración'],
    ['texto' => 'Usuarios',            'ruta' => 'cuentas',        'modulo' => 'SEGURIDAD',              'accion' => 'C', 'listo' => true], // CU02 · Erika
];
