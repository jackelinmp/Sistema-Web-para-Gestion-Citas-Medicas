<?php
namespace App\Controles\Especialidades;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Jornada;
use App\Entidades\Medico;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlDarBajaMedico — CU10 Dar de Baja a un Médico.   Ciclo 2 · P3 · Responsable: Jackeline
 * Interfaces: IUBajaMedico.  Entidades: Medico, Cita, Jornada, Usuario, Bitacora.
 * Permiso: ESPECIALIDADES_MEDICOS/A (anular).
 * Reglas:
 *  - No se elimina al médico: pasa a INACTIVO y conserva su historial (RN11).
 *  - No se puede dar de baja mientras tenga citas futuras PROGRAMADAS o CONFIRMADAS:
 *    primero hay que reprogramarlas o cancelarlas (se muestran en la pantalla).
 *  - Al darlo de baja: su jornada deja de estar vigente y su cuenta de usuario se SUSPENDE.
 *  - Flujo alternativo: reactivar (vuelve a ACTIVO, cuenta HABILITADA; debe configurar su jornada otra vez).
 */
final class ControlDarBajaMedico extends ControlBase
{
    private const MODULO = 'ESPECIALIDADES_MEDICOS';

    public function mostrarBaja(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'A');
        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {                                                 // paso 1: elegir el médico
            $criterio = trim((string) ($_GET['criterio'] ?? ''));
            $this->vista('Especialidades/IUBajaMedico', ['medico' => null, 'criterio' => $criterio,
                                                         'medicos' => (new Medico())->listarMedicos($criterio)]);
            return;
        }
        $medico = $this->obtenerMedico($id);
        $this->vista('Especialidades/IUBajaMedico', [
            'medico'     => $medico,
            'pendientes' => (new Cita())->listarPendientesPorMedico((int) $medico['persona_id']),
            'errores'    => [],
        ]);
    }

    public function confirmarBaja(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'A');
        $medico = $this->obtenerMedico((int) ($_POST['id'] ?? 0));
        $id = (int) $medico['persona_id'];
        $motivo = $this->texto('motivo');
        $pendientes = (new Cita())->listarPendientesPorMedico($id);
        $errores = [];
        if ($medico['estado'] !== 'ACTIVO') {
            $errores['general'] = 'El médico ya está dado de baja.';
        } elseif ($pendientes) {
            $errores['general'] = 'El médico tiene ' . count($pendientes) . ' cita(s) pendiente(s). Reprograme o cancele esas citas antes de darlo de baja.';
        }
        if (mb_strlen($motivo) < 5) {
            $errores['motivo'] = 'Indique el motivo de la baja (mínimo 5 caracteres).';
        }
        if ($errores) {
            $this->vista('Especialidades/IUBajaMedico', ['medico' => $medico, 'pendientes' => $pendientes, 'errores' => $errores]);
            return;
        }
        Conexion::transaccion(function () use ($medico, $id, $motivo) {
            (new Medico())->cambiarEstado($id, 'INACTIVO');
            (new Jornada())->cerrarVigente($id);
            if ($medico['usuario_id']) {
                (new Usuario())->cambiarEstado((int) $medico['usuario_id'], 'SUSPENDIDA');
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), 'BAJA_MEDICO',
                                              'Baja del médico ' . $medico['matricula'] . ' ' . $medico['nombre'] . '. Motivo: ' . $motivo,
                                              $this->ip(), 'medico', $id);
        });
        Sesion::flash('ok', 'El médico ' . $medico['nombre'] . ' fue dado de baja.');
        $this->redirigir('bajas');
    }

    public function reactivarMedico(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'A');
        $medico = $this->obtenerMedico((int) ($_POST['id'] ?? 0));
        if ($medico['estado'] === 'ACTIVO') {
            $this->redirigir('bajas');
        }
        $id = (int) $medico['persona_id'];
        Conexion::transaccion(function () use ($medico, $id) {
            (new Medico())->cambiarEstado($id, 'ACTIVO');
            if ($medico['usuario_id']) {
                (new Usuario())->cambiarEstado((int) $medico['usuario_id'], 'HABILITADA');
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), 'REACTIVAR_MEDICO',
                                              'Reactivación del médico ' . $medico['matricula'] . ' ' . $medico['nombre'],
                                              $this->ip(), 'medico', $id);
        });
        Sesion::flash('ok', 'Médico reactivado. Configure su jornada de atención para que vuelva a tener cupos.');
        $this->redirigir('jornadas&medico_id=' . $id);
    }

    private function obtenerMedico(int $id): array
    {
        $medico = (new Medico())->obtenerPorId($id);
        if (!$medico) {
            Sesion::flash('error', 'El médico no existe.');
            $this->redirigir('bajas');
        }
        return $medico;
    }
}
