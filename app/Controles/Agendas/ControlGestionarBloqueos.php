<?php
namespace App\Controles\Agendas;

use App\Entidades\BloqueoAgenda;
use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Medico;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarBloqueos — CU12 Gestionar Bloqueos de Agenda (RF05).   Ciclo 2 · P4 · Responsable: Jackeline
 * Interfaces: IUBloqueos, IUFormBloqueo.  Entidades: Medico, BloqueoAgenda, Cita, Bitacora.
 * Permisos: AGENDAS/C consultar; AGENDAS/M registrar y quitar. Si a un MEDICO se le da AGENDAS/M (CU03), solo maneja su agenda.
 * Reglas:
 *  - El bloqueo empieza desde ahora; fin > inicio. "Día completo" = desde las 00:00 del primer día hasta las 24:00 del último.
 *  - No puede superponerse con otro bloqueo del mismo médico.
 *  - Si hay citas activas en el periodo, se listan y se pide confirmación: las citas NO se cancelan solas
 *    (hay que reprogramarlas: CU16).
 *  - fn_cupos_disponibles (CU13) ya descuenta los bloqueos: los horarios bloqueados dejan de ofrecerse.
 *  - Quitar un bloqueo lo elimina (la tabla no tiene estado) y queda en la bitácora. Uno ya terminado no se quita.
 */
final class ControlGestionarBloqueos extends ControlBase
{
    private const MODULO = 'AGENDAS';

    public function listarBloqueos(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $medicoId = $this->medicoPermitido((int) ($_GET['medico_id'] ?? 0) ?: null);
        $this->vista('Agendas/IUBloqueos', [
            'medicos'  => $this->esMedico() ? [] : (new Medico())->listarActivos(),
            'medicoId' => $medicoId,
            'bloqueos' => (new BloqueoAgenda())->listarBloqueos($medicoId, date('Y-m-d H:i:s')),
        ]);
    }

    public function mostrarFormulario(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $medicoId = $this->medicoPermitido((int) ($_GET['medico_id'] ?? 0) ?: null);
        $this->vista('Agendas/IUFormBloqueo', [
            'medicos' => $this->esMedico() ? [] : (new Medico())->listarActivos(),
            'bloqueo' => ['medico_id' => $medicoId, 'dia_inicio' => date('Y-m-d'), 'dia_fin' => date('Y-m-d'),
                          'dia_completo' => true, 'tipo_bloqueo' => 'VACACIONES'],
            'errores' => [], 'citasAfectadas' => [],
        ]);
    }

    public function guardarBloqueo(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $d = $this->leerFormulario();
        $errores = $this->validar($d);
        $afectadas = $errores ? [] : (new Cita())->listarEnPeriodo($d['medico_id'], $d['fecha_inicio'], $d['fecha_fin']);
        $confirmado = ($_POST['confirmar'] ?? '') === '1';

        if ($errores || ($afectadas && !$confirmado)) {
            $this->vista('Agendas/IUFormBloqueo', [
                'medicos' => $this->esMedico() ? [] : (new Medico())->listarActivos(),
                'bloqueo' => $d, 'errores' => $errores, 'citasAfectadas' => $afectadas,
            ]);
            return;
        }
        Conexion::transaccion(function () use ($d, $afectadas) {
            $id = (new BloqueoAgenda())->insertar($d);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'REGISTRAR_BLOQUEO',
                                              BloqueoAgenda::TIPOS[$d['tipo_bloqueo']] . ' del médico ' . $d['medico_id'] . ' del '
                                              . $d['fecha_inicio'] . ' al ' . $d['fecha_fin']
                                              . ($afectadas ? ' (' . count($afectadas) . ' citas por reprogramar)' : ''),
                                              $this->ip(), 'bloqueo_agenda', $id);
        });
        Sesion::flash($afectadas ? 'aviso' : 'ok', 'Bloqueo registrado.'
                      . ($afectadas ? ' Quedan ' . count($afectadas) . ' cita(s) dentro del bloqueo: comuníquese con los pacientes para reprogramarlas.' : ''));
        $this->redirigir('bloqueos&medico_id=' . $d['medico_id']);
    }

    public function quitarBloqueo(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $entidad = new BloqueoAgenda();
        $bloqueo = $entidad->obtenerPorId((int) ($_POST['id'] ?? 0));
        if (!$bloqueo || $this->medicoPermitido((int) $bloqueo['medico_id']) !== (int) $bloqueo['medico_id']) {
            $this->redirigir('bloqueos');
        }
        if ($bloqueo['fecha_fin'] <= date('Y-m-d H:i:s')) {
            Sesion::flash('error', 'No se puede quitar un bloqueo que ya terminó.');
            $this->redirigir('bloqueos&medico_id=' . $bloqueo['medico_id']);
        }
        Conexion::transaccion(function () use ($entidad, $bloqueo) {
            $entidad->eliminar((int) $bloqueo['id']);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'QUITAR_BLOQUEO',
                                              'Bloqueo de ' . $bloqueo['medico'] . ' del ' . $bloqueo['fecha_inicio'] . ' al ' . $bloqueo['fecha_fin']
                                              . ' (' . $bloqueo['tipo_bloqueo'] . ')',
                                              $this->ip(), 'bloqueo_agenda', (int) $bloqueo['id']);
        });
        Sesion::flash('ok', 'Bloqueo quitado. Los horarios vuelven a estar disponibles.');
        $this->redirigir('bloqueos&medico_id=' . $bloqueo['medico_id']);
    }

    /** Arma fecha_inicio y fecha_fin (timestamp) a partir de los días y horas del formulario. */
    private function leerFormulario(): array
    {
        $d = [
            'medico_id'    => $this->medicoPermitido((int) ($_POST['medico_id'] ?? 0) ?: null),
            'dia_inicio'   => $this->texto('dia_inicio'),
            'dia_fin'      => $this->texto('dia_fin') ?: $this->texto('dia_inicio'),
            'dia_completo' => !empty($_POST['dia_completo']),
            'hora_inicio'  => $this->texto('hora_inicio'),
            'hora_fin'     => $this->texto('hora_fin'),
            'tipo_bloqueo' => $this->texto('tipo_bloqueo'),
            'motivo'       => $this->texto('motivo') ?: null,
        ];
        $okDias = $this->esFecha($d['dia_inicio']) && $this->esFecha($d['dia_fin']);
        if ($d['dia_completo']) {
            $d['fecha_inicio'] = $okDias ? $d['dia_inicio'] . ' 00:00:00' : '';
            $d['fecha_fin'] = $okDias ? date('Y-m-d', strtotime($d['dia_fin'] . ' +1 day')) . ' 00:00:00' : '';
        } else {
            $okHoras = preg_match('/^\d{2}:\d{2}$/', $d['hora_inicio']) && preg_match('/^\d{2}:\d{2}$/', $d['hora_fin']);
            $d['fecha_inicio'] = $okDias && $okHoras ? $d['dia_inicio'] . ' ' . $d['hora_inicio'] . ':00' : '';
            $d['fecha_fin'] = $okDias && $okHoras ? $d['dia_fin'] . ' ' . $d['hora_fin'] . ':00' : '';
        }
        return $d;
    }

    private function validar(array $d): array
    {
        $e = [];
        $medico = $d['medico_id'] ? (new Medico())->obtenerPorId($d['medico_id']) : null;
        if (!$medico || $medico['estado'] !== 'ACTIVO') {
            $e['medico_id'] = 'Seleccione un médico activo.';
        }
        if ($d['fecha_inicio'] === '' || $d['fecha_fin'] === '') {
            $e['dia_inicio'] = $d['dia_completo'] ? 'Ingrese fechas válidas.' : 'Ingrese fechas y horas válidas, o marque "Día completo".';
        } elseif ($d['dia_fin'] < $d['dia_inicio']) {
            $e['dia_fin'] = 'La fecha de fin no puede ser anterior a la de inicio';
        } elseif ($d['fecha_fin'] <= $d['fecha_inicio']) {
            $e['hora_inicio'] = 'La hora de fin debe ser posterior a la hora de inicio';
        } elseif ($d['fecha_fin'] <= date('Y-m-d H:i:s') || $d['dia_inicio'] < date('Y-m-d')) {
            $e['dia_inicio'] = 'El bloqueo debe ser desde hoy en adelante';
        }
        if (!isset(BloqueoAgenda::TIPOS[$d['tipo_bloqueo']])) {
            $e['tipo_bloqueo'] = 'Seleccione el tipo de bloqueo.';
        }
        if ($d['motivo'] !== null && mb_strlen($d['motivo']) > 200) {
            $e['motivo'] = 'Máximo 200 caracteres.';
        }
        if (!$e && (new BloqueoAgenda())->existeSuperpuesto($d['medico_id'], $d['fecha_inicio'], $d['fecha_fin'])) {
            $e['general'] = 'Ya existe un bloqueo de este médico que se superpone con ese periodo.';
        }
        return $e;
    }

    private function esFecha(string $v): bool
    {
        $f = \DateTime::createFromFormat('Y-m-d', $v);
        return $f && $f->format('Y-m-d') === $v;
    }

    private function esMedico(): bool
    {
        return (Sesion::usuario()['rol'] ?? '') === 'MEDICO';
    }

    /** El rol MEDICO siempre trabaja sobre su propia agenda, aunque envíe otro id. */
    private function medicoPermitido(?int $medicoId): ?int
    {
        return $this->esMedico() ? (int) Sesion::usuario()['persona_id'] : $medicoId;
    }
}
