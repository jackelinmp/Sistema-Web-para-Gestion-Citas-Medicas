<?php
namespace App\Controles\Citas;

use App\Controles\Pacientes\ControlValidarRestricciones;
use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Especialidad;
use App\Entidades\Medico;
use App\Entidades\Paciente;
use App\Entidades\Pago;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlAgendarCita — CU14 Agendar Cita Médica (RF06, RF07).   Responsable: Jackeline
 * Interfaces: IUAgendarCita.  Entidades: Paciente, Especialidad, Medico, Cita, Pago, Bitacora.
 * Incluye («include»): CU06 ControlValidarRestricciones y CU13 ControlVerificarDisponibilidad.
 * Asistente de 4 pasos por parámetros GET: paciente_id -> especialidad_id + fecha (+ medico_id) -> cupo -> confirmar.
 */
final class ControlAgendarCita extends ControlBase
{
    private const MODULO = 'CITAS';
    private const ORIGEN = ['RECEPCIONISTA' => 'RECEPCION', 'MEDICO' => 'MEDICO', 'PACIENTE' => 'PORTAL'];

    /** Pasos 1 a 3: buscar paciente, especialidades permitidas, cupos libres. */
    public function mostrarFormulario(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'R');
        $disp = new ControlVerificarDisponibilidad();
        $p = $this->leerParametros($_GET);
        $datos = ['p' => $p, 'pacientes' => [], 'paciente' => null, 'especialidades' => [], 'medicos' => [],
                  'cupos' => null, 'resumen' => null, 'error' => null];

        if (!$p['paciente_id']) {                                   // paso 1
            $datos['pacientes'] = $p['criterio'] !== '' ? (new Paciente())->buscar($p['criterio']) : [];
            $this->vista('Citas/IUAgendarCita', $datos);
            return;
        }
        $datos['paciente'] = (new Paciente())->obtenerPorId($p['paciente_id']);
        if (!$datos['paciente'] || $datos['paciente']['estado'] !== 'ACTIVO') {
            Sesion::flash('error', 'Seleccione un paciente activo.');
            $this->redirigir('citas/nueva');
        }
        $datos['error'] = $disp->validarFecha($p['fecha']);
        if (!$datos['error']) {                                     // paso 2 («include» CU06)
            $datos['especialidades'] = (new ControlValidarRestricciones())->obtenerEspecialidadesPermitidas($p['paciente_id'], $p['fecha']);
            if (!$datos['especialidades']) {
                $datos['error'] = 'No hay especialidades disponibles para la edad y el sexo del paciente';
            }
        }
        if (!$datos['error'] && $p['especialidad_id']) {            // paso 3 («include» CU13)
            if (!in_array($p['especialidad_id'], array_map('intval', array_column($datos['especialidades'], 'id')), true)) {
                $datos['error'] = 'La especialidad seleccionada no corresponde a la edad o sexo del paciente';
            } else {
                $datos['medicos'] = (new Medico())->listarPorEspecialidad($p['especialidad_id']);
                $datos['cupos'] = $disp->consultarCupos($p['especialidad_id'], $p['medico_id'], $p['fecha']);
                if (!$datos['medicos']) {
                    $datos['error'] = 'No hay médicos disponibles para la especialidad seleccionada';
                }
            }
        }
        if (!$datos['error'] && $p['medico_id'] && $p['hora_inicio'] !== '') {   // paso 4: resumen
            $datos['resumen'] = $this->mostrarResumen($p);
        }
        $this->vista('Citas/IUAgendarCita', $datos);
    }

    /** Resumen antes de confirmar: paciente, especialidad, médico, fecha, hora y costo. */
    public function mostrarResumen(array $p): ?array
    {
        $medico = (new Medico())->obtenerPorId($p['medico_id']);
        $especialidad = (new Especialidad())->obtenerPorId($p['especialidad_id']);
        return ($medico && $especialidad) ? ['medico' => $medico['nombre'], 'especialidad' => $especialidad['nombre'],
                                              'costo' => $especialidad['costo_consulta']] : null;
    }

    /** Paso 4: confirmar. Vuelve a verificar el cupo y registra cita + pago pendiente en una sola transacción (RNF09). */
    public function confirmarCita(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'R');
        $p = $this->leerParametros($_POST);
        $volver = 'citas/nueva&' . http_build_query(array_intersect_key($p, array_flip(
            ['paciente_id', 'especialidad_id', 'medico_id', 'fecha'])));
        $permitidas = (new ControlValidarRestricciones())->obtenerEspecialidadesPermitidas($p['paciente_id'], $p['fecha']);
        $especialidad = (new Especialidad())->obtenerPorId($p['especialidad_id']);
        if (!$especialidad || !in_array($p['especialidad_id'], array_map('intval', array_column($permitidas, 'id')), true)) {
            Sesion::flash('error', 'La especialidad seleccionada no corresponde a la edad o sexo del paciente');
            $this->redirigir($volver);
        }
        try {
            $cita = Conexion::transaccion(function () use ($p, $especialidad) {
                $cupo = (new ControlVerificarDisponibilidad())->verificarCupoLibre($p['medico_id'], $p['fecha'], $p['hora_inicio']);
                if (!$cupo) {
                    throw new \DomainException('El horario seleccionado ya no está disponible');
                }
                $usuario = Sesion::usuario();
                $cita = (new Cita())->insertar([
                    'paciente_id'     => $p['paciente_id'],
                    'medico_id'       => $p['medico_id'],
                    'especialidad_id' => $p['especialidad_id'],
                    'fecha'           => $p['fecha'],
                    'hora_inicio'     => $cupo['hora_inicio'],
                    'hora_fin'        => $cupo['hora_fin'],
                    'motivo_consulta' => $p['motivo_consulta'] ?: null,
                    'origen'          => self::ORIGEN[$usuario['rol']] ?? 'RECEPCION',
                    'registrado_por'  => (int) $usuario['id'],
                ]);
                (new Pago())->insertar((int) $cita['id'], (string) $especialidad['costo_consulta'], (int) $usuario['id']);
                (new Bitacora())->registrarEvento((int) $usuario['id'], 'AGENDAR_CITA',
                                                  'Cita ' . $cita['codigo'] . ' del ' . $p['fecha'] . ' ' . $cupo['hora_inicio'],
                                                  $this->ip(), 'cita', (int) $cita['id']);
                return $cita;
            });
        } catch (\DomainException $e) {
            Sesion::flash('error', $e->getMessage());
            $this->redirigir($volver);
        } catch (\PDOException $e) {
            Sesion::flash('error', $this->mensajeBaseDatos($e));
            $this->redirigir($volver);
        }
        Sesion::flash('ok', 'Cita ' . $cita['codigo'] . ' registrada en estado Programada.');
        $this->redirigir('citas/constancia&id=' . (int) $cita['id']);
    }

    /** Constancia de la cita registrada. */
    public function mostrarConstancia(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $cita = (new Cita())->obtenerDetalle((int) ($_GET['id'] ?? 0));
        if (!$cita) {
            $this->redirigir('citas/nueva');
        }
        $u = Sesion::usuario();
        if ($u['rol'] === 'PACIENTE' && (int) $cita['paciente_id'] !== (int) $u['persona_id']) {
            Sesion::denegar('Intento de ver la cita ' . (int) $cita['id'] . ' de otro paciente');
        }
        $this->vista('Citas/IUConstanciaCita', ['cita' => $cita]);
    }

    private function leerParametros(array $origen): array
    {
        $usuario = Sesion::usuario();
        $pacienteId = (int) ($origen['paciente_id'] ?? 0) ?: null;
        if (($usuario['rol'] ?? '') === 'PACIENTE') {               // el paciente solo agenda para sí mismo
            $pacienteId = (int) $usuario['persona_id'];
        }
        return [
            'criterio'        => trim((string) ($origen['criterio'] ?? '')),
            'paciente_id'     => $pacienteId,
            'especialidad_id' => (int) ($origen['especialidad_id'] ?? 0) ?: null,
            'medico_id'       => (int) ($origen['medico_id'] ?? 0) ?: null,
            'fecha'           => (string) ($origen['fecha'] ?? date('Y-m-d')),
            'hora_inicio'     => (string) ($origen['hora_inicio'] ?? ''),
            'motivo_consulta' => trim((string) ($origen['motivo_consulta'] ?? '')),
        ];
    }

    /** Traduce los rechazos de la base (EXCLUDE y triggers) a los mensajes del CU. */
    private function mensajeBaseDatos(\PDOException $e): string
    {
        $m = $e->getMessage();
        if (str_contains($m, 'ex_cita_paciente')) {
            return 'El paciente ya tiene una cita en ese horario';
        }
        if (str_contains($m, 'ex_cita_medico')) {
            return 'El horario seleccionado ya no está disponible';
        }
        if (preg_match('/ERROR:\s+(.+?)(\n|CONTEXT|$)/s', $m, $x)) {   // RAISE EXCEPTION de tg_validar_cita
            return trim($x[1]);
        }
        return 'No se pudo registrar la cita. Intente nuevamente.';
    }
}
