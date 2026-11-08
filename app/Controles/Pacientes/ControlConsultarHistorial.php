<?php
namespace App\Controles\Pacientes;

use App\Entidades\Cita;
use App\Entidades\FichaClinica;
use App\Entidades\Paciente;
use App\Entidades\SolicitudExamen;
use App\Entidades\VinculoFamiliar;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlConsultarHistorial — CU07 Consultar Historial del Paciente.   Ciclo 2 · P8 · Responsable: Jackeline
 * Interfaces: IUHistorialPaciente (+ parcial IUBuscarPaciente).
 * Entidades: Paciente, FichaClinica, Cita, SolicitudExamen, VinculoFamiliar.
 * Solo lectura. Permiso PACIENTES/C (administrador, recepcionista y médico).
 */
final class ControlConsultarHistorial extends ControlBase
{
    private const MODULO = 'PACIENTES';
    public const ESTADOS_CITA = ['PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA'];

    public function mostrarHistorial(): void
    {
        $pacienteId = $this->exigirAccesoPaciente(self::MODULO, (int) ($_GET['paciente_id'] ?? 0) ?: null);
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        if (!$pacienteId) {                                         // paso 1: buscar paciente
            $this->vista('Pacientes/IUHistorialPaciente', [
                'paciente' => null, 'criterio' => $criterio,
                'pacientes' => $criterio !== '' ? (new Paciente())->listarPacientes($criterio) : [],
            ]);
            return;
        }
        if ($this->mostrarSinExpediente($pacienteId, 'Historial clínico', 'historial')) {
            return;
        }
        $paciente = (new Paciente())->obtenerPorId($pacienteId);
        if (!$paciente) {
            Sesion::flash('error', 'El paciente no existe.');
            $this->redirigir('historial');
        }
        $filtros = [
            'desde'  => $this->fechaDeFiltro('desde'),
            'hasta'  => $this->fechaDeFiltro('hasta'),
            'estado' => in_array($_GET['estado'] ?? '', self::ESTADOS_CITA, true) ? $_GET['estado'] : '',
        ];
        $fichas = new FichaClinica();
        $fichas->crearFichaVacia($pacienteId);                      // pacientes antiguos que aún no tienen ficha
        $citas = (new Cita())->listarPorPaciente($pacienteId, $filtros['desde'], $filtros['hasta'], $filtros['estado']);
        $vinculos = new VinculoFamiliar();
        $this->vista('Pacientes/IUHistorialPaciente', [
            'paciente'   => $paciente,
            'ficha'      => $fichas->obtenerPorPaciente($pacienteId),
            'citas'      => $citas,
            'resumen'    => $this->contarPorEstado($citas),
            'examenes'   => (new SolicitudExamen())->listarPorPaciente($pacienteId, false),
            'familiares' => $vinculos->listarFamiliares($pacienteId),
            'titulares'  => $vinculos->listarTitulares($pacienteId),
            'filtros'    => $filtros,
        ]);
    }

    /** Cantidad de citas por estado (indicadores de la parte superior). */
    private function contarPorEstado(array $citas): array
    {
        $conteo = array_fill_keys(self::ESTADOS_CITA, 0);
        foreach ($citas as $c) {
            $conteo[$c['estado']] = ($conteo[$c['estado']] ?? 0) + 1;
        }
        return $conteo;
    }
}
