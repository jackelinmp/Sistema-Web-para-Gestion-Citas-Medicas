<?php
namespace App\Controles\Citas;

use App\Entidades\Cita;
use App\Entidades\Especialidad;
use App\Entidades\Medico;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlVerificarDisponibilidad — CU13 Verificar Disponibilidad Horaria (RF05, RF07).   Responsable: Jackeline
 * Interfaces: IUDisponibilidad.  Entidades: Especialidad, Medico, Jornada, BloqueoAgenda, Cita.
 * Los cupos se calculan en la base con fn_cupos_disponibles (jornada - citas activas - bloqueos - horas pasadas).
 * También lo usa ControlAgendarCita («include», CU14).
 */
final class ControlVerificarDisponibilidad extends ControlBase
{
    private const MODULO = 'CITAS';

    public function mostrarFiltros(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $especialidadId = (int) ($_GET['especialidad_id'] ?? 0) ?: null;
        $medicoId = (int) ($_GET['medico_id'] ?? 0) ?: null;
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $error = null;
        $resultado = null;
        if ($especialidadId) {
            $error = $this->validarFecha($fecha);
            if (!$error) {
                $resultado = $this->consultarCupos($especialidadId, $medicoId, $fecha);
                if (!$resultado) {
                    $error = 'No hay médicos disponibles para la especialidad seleccionada';
                }
            }
        }
        $this->vista('Citas/IUDisponibilidad', [
            'especialidades' => (new Especialidad())->listarActivas(),
            'medicos'        => $especialidadId ? (new Medico())->listarPorEspecialidad($especialidadId) : [],
            'filtros'        => ['especialidad_id' => $especialidadId, 'medico_id' => $medicoId, 'fecha' => $fecha],
            'resultado'      => $resultado,
            'error'          => $error,
        ]);
    }

    /** Cupos libres agrupados por médico: [['medico' => [...], 'cupos' => [...]], ...] */
    public function consultarCupos(int $especialidadId, ?int $medicoId, string $fecha): array
    {
        $citas = new Cita();
        $resultado = [];
        foreach ((new Medico())->listarPorEspecialidad($especialidadId) as $m) {
            if ($medicoId && (int) $m['persona_id'] !== $medicoId) {
                continue;
            }
            $resultado[] = ['medico' => $m, 'cupos' => $citas->cuposDisponibles((int) $m['persona_id'], $fecha)];
        }
        return $resultado;
    }

    /** Verificación final antes de guardar (RN13): devuelve el cupo con su hora_fin o null si ya no está libre. */
    public function verificarCupoLibre(int $medicoId, string $fecha, string $horaInicio): ?array
    {
        foreach ((new Cita())->cuposDisponibles($medicoId, $fecha) as $cupo) {
            if (substr($cupo['hora_inicio'], 0, 5) === substr($horaInicio, 0, 5)) {
                return $cupo;
            }
        }
        return null;
    }

    public function validarFecha(string $fecha): ?string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return 'Seleccione una fecha válida.';
        }
        return $fecha < date('Y-m-d') ? 'La fecha debe ser igual o posterior a la fecha actual' : null;
    }
}
