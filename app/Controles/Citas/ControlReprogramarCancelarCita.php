<?php
namespace App\Controles\Citas;

use App\Controles\Comunicacion\ControlGestionarNotificaciones;
use App\Entidades\Cita;
use App\Entidades\Medico;
use App\Entidades\ParametroSistema;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlReprogramarCancelarCita — CU16 Reprogramar y cancelar cita.
 * Interfaces: IUMisCitas (lista), IUReprogramarCita (nuevo horario o cancelación).
 * Entidades: Cita, Medico, ParametroSistema.  «include»: CU13 ControlVerificarDisponibilidad, CU24 notificaciones.
 *
 * Reglas del negocio:
 *  - Actores: Paciente (solo sus citas), Recepcionista y Administrador. El médico no cancela citas:
 *    sus ausencias se registran como bloqueo de agenda (CU12).
 *  - Solo se cambian citas Programadas o Confirmadas que aún no empezaron.
 *  - El paciente debe hacerlo con HORAS_MINIMAS_CAMBIO de anticipación (24 h) y puede reprogramar
 *    una misma cita hasta MAX_REPROGRAMACIONES veces (2). Pasado eso, debe llamar a recepción.
 *  - Reprogramar mueve la MISMA cita (conserva código y pago) a otro cupo libre de la misma
 *    especialidad; el cupo anterior queda libre solo. Cancelar exige un motivo; el pago pendiente
 *    se anula y el pagado queda REEMBOLSADO (la devolución la hace recepción).
 */
final class ControlReprogramarCancelarCita extends ControlBase
{
    private const MODULO = 'CITAS';
    private const ROLES = ['ADMINISTRADOR', 'RECEPCIONISTA', 'PACIENTE'];

    /** Citas próximas: el paciente ve las suyas; el personal busca por paciente, CI o código. */
    public function listarCitas(): void
    {
        $this->exigirAcceso();
        $esPaciente = $this->esPaciente();
        $criterio = $esPaciente ? '' : trim((string) ($_GET['criterio'] ?? ''));
        $this->vista('Citas/IUMisCitas', [
            'citas'      => (new Cita())->listarProximas($esPaciente ? (int) Sesion::usuario()['persona_id'] : null, $criterio),
            'criterio'   => $criterio,
            'esPaciente' => $esPaciente,
        ]);
    }

    /** Detalle de la cita con los cupos libres para moverla (CU13) y el formulario de cancelación. */
    public function mostrarCita(): void
    {
        $this->exigirAcceso();
        $citas = new Cita();
        $cita = $citas->obtenerDetalle((int) ($_GET['id'] ?? 0));
        if (!$cita) {
            Sesion::flash('error', 'La cita indicada no existe.');
            $this->redirigir('citas/proximas');
        }
        $this->exigirDueno($cita);

        $disponibilidad = new ControlVerificarDisponibilidad();
        $fecha = (string) ($_GET['fecha'] ?? '');
        $medicoId = (int) ($_GET['medico_id'] ?? 0) ?: null;
        $horaInicio = (string) ($_GET['hora_inicio'] ?? '');
        $bloqueo = $this->validarCambio($cita, false);
        $limite = $bloqueo ? null : $this->validarCambio($cita, true);   // tope de reprogramaciones del paciente
        $error = null;
        $cupos = null;
        if (!$bloqueo && !$limite && $fecha !== '') {
            $error = $disponibilidad->validarFecha($fecha);
            if (!$error) {
                $cupos = $disponibilidad->consultarCupos((int) $cita['especialidad_id'], $medicoId, $fecha);
            }
        }
        $this->vista('Citas/IUReprogramarCita', [
            'cita'       => $cita,
            'medicos'    => (new Medico())->listarPorEspecialidad((int) $cita['especialidad_id']),
            'fecha'      => $fecha,
            'medicoId'   => $medicoId,
            'horaInicio' => $horaInicio,
            'cupos'      => $cupos,
            'bloqueo'    => $bloqueo,
            'limite'     => $limite,
            'error'      => $error,
            'nuevoMedico' => ($medicoId && $horaInicio !== '') ? (new Medico())->obtenerPorId($medicoId) : null,
        ]);
    }

    /** Mueve la cita al cupo elegido. Vuelve a verificar el cupo dentro de la transacción (RN13). */
    public function reprogramarCita(): void
    {
        $this->exigirAcceso();
        $cita = $this->obtenerCitaDelFormulario();
        $volver = 'citas/cambiar&id=' . (int) $cita['id'];
        if ($error = $this->validarCambio($cita, true)) {
            Sesion::flash('error', $error);
            $this->redirigir($volver);
        }
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $horaInicio = (string) ($_POST['hora_inicio'] ?? '');
        if ($fecha === $cita['fecha'] && substr($horaInicio, 0, 5) === substr($cita['hora_inicio'], 0, 5)
            && $medicoId === (int) $cita['medico_id']) {
            Sesion::flash('error', 'Elija un horario distinto al actual.');
            $this->redirigir($volver);
        }
        $medicosValidos = array_map('intval', array_column((new Medico())->listarPorEspecialidad((int) $cita['especialidad_id']), 'persona_id'));
        if (!in_array($medicoId, $medicosValidos, true)) {
            Sesion::flash('error', 'El médico elegido no atiende esta especialidad.');
            $this->redirigir($volver);
        }
        try {
            $cupo = Conexion::transaccion(function () use ($cita, $medicoId, $fecha, $horaInicio) {
                $cupo = (new ControlVerificarDisponibilidad())->verificarCupoLibre($medicoId, $fecha, $horaInicio);
                if (!$cupo) {
                    throw new \DomainException('El horario seleccionado ya no está disponible');
                }
                (new Cita())->reprogramar((int) $cita['id'], $medicoId, $fecha, $cupo['hora_inicio'], $cupo['hora_fin'],
                                          (int) $this->idUsuario());
                return $cupo;
            });
        } catch (\DomainException $e) {
            Sesion::flash('error', $e->getMessage());
            $this->redirigir($volver);
        } catch (\PDOException $e) {
            Sesion::flash('error', $this->mensajeBaseDatos($e));
            $this->redirigir($volver);
        }
        (new ControlGestionarNotificaciones())->notificarCita(
            (int) $cita['id'], 'SISTEMA',
            'Su cita fue reprogramada',
            'Su cita ' . $cita['codigo'] . ' fue reprogramada para el ' . date('d/m/Y', strtotime($fecha))
            . ' a las ' . substr($cupo['hora_inicio'], 0, 5) . '.'
        );
        Sesion::flash('ok', 'Cita ' . $cita['codigo'] . ' reprogramada para el ' . date('d/m/Y', strtotime($fecha))
                            . ' a las ' . substr($cupo['hora_inicio'], 0, 5) . '. El horario anterior quedó libre.');
        $this->redirigir('citas/proximas');
    }

    /** Cancela la cita con su motivo; el cupo queda libre y el pago se anula o pasa a reembolso. */
    public function cancelarCita(): void
    {
        $this->exigirAcceso();
        $cita = $this->obtenerCitaDelFormulario();
        $volver = 'citas/cambiar&id=' . (int) $cita['id'];
        if ($error = $this->validarCambio($cita, false)) {
            Sesion::flash('error', $error);
            $this->redirigir($volver);
        }
        $motivo = $this->texto('motivo');
        if (mb_strlen($motivo) < 5) {
            Sesion::flash('error', 'Indique el motivo de la cancelación.');
            $this->redirigir($volver);
        }
        try {
            Conexion::transaccion(fn() => (new Cita())->cancelar((int) $cita['id'], mb_substr($motivo, 0, 200), (int) $this->idUsuario()));
        } catch (\PDOException $e) {
            Sesion::flash('error', $this->mensajeBaseDatos($e));
            $this->redirigir($volver);
        }
        (new ControlGestionarNotificaciones())->notificarCita(
            (int) $cita['id'], 'CANCELACION',
            'Su cita fue cancelada',
            'Su cita ' . $cita['codigo'] . ' fue cancelada. Motivo: ' . mb_substr($motivo, 0, 200) . '.'
        );
        $detalle = (new Cita())->obtenerDetalle((int) $cita['id']);
        $aviso = ($detalle['pago_estado'] ?? '') === 'REEMBOLSADO'
            ? ' El pago de Bs ' . number_format((float) $detalle['monto'], 2, ',', '.') . ' queda para devolución en recepción.' : '';
        Sesion::flash('ok', 'Cita ' . $cita['codigo'] . ' cancelada. El horario quedó libre.' . $aviso);
        $this->redirigir('citas/proximas');
    }

    /**
     * Reglas de CU16. Devuelve el motivo por el que no se puede cambiar, o null si se puede.
     * $esReprogramacion agrega el límite de reprogramaciones del paciente.
     */
    private function validarCambio(array $cita, bool $esReprogramacion): ?string
    {
        if (!in_array($cita['estado'], ['PROGRAMADA', 'CONFIRMADA'], true)) {
            return 'Solo se puede cambiar una cita programada o confirmada.';
        }
        $inicio = strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']);
        if ($inicio <= time()) {
            return 'La cita ya empezó o pasó; no se puede cambiar.';
        }
        if (!$this->esPaciente()) {
            return null;
        }
        $parametros = new ParametroSistema();
        $horas = (int) $parametros->obtenerValor('HORAS_MINIMAS_CAMBIO', '24');
        if ($inicio - time() < $horas * 3600) {
            return "Faltan menos de $horas horas para la cita: para cambiarla comuníquese con recepción.";
        }
        $maximo = (int) $parametros->obtenerValor('MAX_REPROGRAMACIONES', '2');
        if ($esReprogramacion && (int) $cita['veces_reprogramada'] >= $maximo) {
            return "Esta cita ya se reprogramó $maximo veces: para moverla otra vez comuníquese con recepción.";
        }
        return null;
    }

    private function obtenerCitaDelFormulario(): array
    {
        $cita = (new Cita())->obtenerParaGestion((int) ($_POST['cita_id'] ?? 0));
        if (!$cita) {
            Sesion::flash('error', 'La cita indicada no existe.');
            $this->redirigir('citas/proximas');
        }
        $this->exigirDueno($cita);
        return $cita;
    }

    private function exigirAcceso(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'R');
        if (!in_array((string) (Sesion::usuario()['rol'] ?? ''), self::ROLES, true)) {
            Sesion::denegar('Rol sin acceso a reprogramar o cancelar citas');
        }
    }

    /** El paciente solo puede tocar sus propias citas. */
    private function exigirDueno(array $cita): void
    {
        if ($this->esPaciente() && (int) $cita['paciente_id'] !== (int) Sesion::usuario()['persona_id']) {
            Sesion::denegar('Intento de cambiar la cita ' . (int) $cita['id'] . ' de otro paciente');
        }
    }


}
