<?php
namespace App\Controles\Pacientes;

use App\Entidades\Especialidad;
use App\Entidades\Paciente;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlValidarRestricciones — CU06 Validar Edad y Restricciones («include» de CU14, junto con CU13).
 * La regla se calcula en la base con fn_especialidades_permitidas (edad a la fecha de la cita y sexo)
 * y el trigger tg_validar_cita la vuelve a comprobar al guardar. Interfaz de consulta: IURestricciones.
 */
final class ControlValidarRestricciones extends ControlBase
{
    public function obtenerEspecialidadesPermitidas(int $pacienteId, string $fecha): array
    {
        return (new Especialidad())->listarPermitidas($pacienteId, $fecha);
    }

    /** Pantalla de consulta: por cada especialidad activa, si el paciente puede o no atenderse y por qué. */
    public function consultarRestricciones(): void
    {
        Sesion::exigirPermiso('CITAS', 'C');
        $u = Sesion::usuario();
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['fecha'] ?? '')) ? $_GET['fecha'] : date('Y-m-d');
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        $pacienteId = $u['rol'] === 'PACIENTE' ? (int) $u['persona_id'] : (int) ($_GET['paciente_id'] ?? 0);
        $datos = ['fecha' => $fecha, 'criterio' => $criterio, 'pacientes' => [], 'paciente' => null, 'filas' => []];
        if (!$pacienteId) {
            $datos['pacientes'] = $criterio !== '' ? (new Paciente())->buscar($criterio) : [];
        } else {
            $datos['paciente'] = (new Paciente())->obtenerPorId($pacienteId);
            if ($datos['paciente']) {
                $esp = new Especialidad();
                $permitidas = array_map('intval', array_column($esp->listarPermitidas($pacienteId, $fecha), 'id'));
                $datos['edad'] = (int) (new \DateTime($datos['paciente']['fecha_nacimiento']))->diff(new \DateTime($fecha))->y;
                foreach ($esp->listarActivas() as $e) {
                    $det = $esp->obtenerPorId((int) $e['id']);
                    $ok = in_array((int) $e['id'], $permitidas, true);
                    $motivo = '';
                    if (!$ok) {
                        $sexoPac = $datos['paciente']['sexo'] ?? '';
                        if ($det['sexo_permitido'] !== 'A' && $det['sexo_permitido'] !== $sexoPac) {
                            $motivo = 'Solo para sexo ' . ($det['sexo_permitido'] === 'F' ? 'femenino' : 'masculino');
                        } else {
                            $motivo = 'Edad fuera del rango (' . $det['edad_minima'] . ($det['edad_maxima'] !== null ? '–' . $det['edad_maxima'] : '+') . ' años)';
                        }
                    }
                    $datos['filas'][] = ['nombre' => $e['nombre'], 'costo' => $e['costo_consulta'], 'ok' => $ok, 'motivo' => $motivo,
                                         'rango' => $det['edad_minima'] . ($det['edad_maxima'] !== null ? '–' . $det['edad_maxima'] : '+') . ' años',
                                         'sexo' => ['A' => 'Todos', 'F' => 'Femenino', 'M' => 'Masculino'][$det['sexo_permitido']] ?? 'Todos'];
                }
            }
        }
        $this->vista('Pacientes/IURestricciones', $datos);
    }
}
