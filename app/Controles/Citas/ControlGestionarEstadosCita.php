<?php
namespace App\Controles\Citas;

use App\Entidades\Cita;
use App\Entidades\Medico;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarEstadosCita — CU15 Gestionar Estados de Cita (RF15).
 * También atiende CU17 Registrar Inasistencia, que es una extensión («extend») de CU15.
 *
 * Reglas del CU:
 *  - Recepcionista confirma una cita Programada.
 *  - Médico marca como Atendida una cita Confirmada, solo de su propia agenda.
 *  - Recepcionista o Médico registran la Inasistencia cuando la hora ya pasó.
 *  - La cancelación y la reprogramación pertenecen a CU16.
 *
 * La transición válida y la bitácora las controla sp_cambiar_estado_cita; aquí solo
 * se valida el rol, la propiedad de la cita y el momento en que se puede aplicar.
 */
final class ControlGestionarEstadosCita extends ControlBase
{
    private const MODULO = 'CITAS';
    private const ROLES_AGENDA = ['ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO'];

    /** Agenda del día: lista las citas y muestra las acciones permitidas según el estado. */
    public function listarAgenda(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $this->exigirRol(self::ROLES_AGENDA);

        $usuario  = Sesion::usuario();
        $fecha    = (string) ($_GET['fecha'] ?? date('Y-m-d'));
        $estado   = (string) ($_GET['estado'] ?? '');
        $esMedico = ($usuario['rol'] ?? '') === 'MEDICO';
        // El médico solo ve su agenda; los demás pueden filtrar por cualquier médico.
        $medicoId = $esMedico
            ? (int) $usuario['persona_id']
            : (((string) ($_GET['medico_id'] ?? '')) !== '' ? (int) $_GET['medico_id'] : null);

        $this->vista('Citas/IUAgendaDia', [
            'citas'    => (new Cita())->listarAgenda($fecha, $estado, $medicoId),
            'medicos'  => $esMedico ? [] : (new Medico())->listarActivos(),
            'medicoId' => $medicoId,
            'fecha'    => $fecha,
            'estado'   => $estado,
        ]);
    }

    /** CU15: Programada → Confirmada. */
    public function confirmarCita(): void
    {
        $cita = $this->prepararCambio(['PROGRAMADA'], ['ADMINISTRADOR', 'RECEPCIONISTA']);
        $this->aplicarEstado($cita, 'CONFIRMADA', 'Cita ' . $cita['codigo'] . ' confirmada.');
    }

    /** CU15: Confirmada → Atendida, solo cuando la fecha y hora de la cita ya pasaron. */
    public function marcarAtendida(): void
    {
        $cita = $this->prepararCambio(['CONFIRMADA'], ['ADMINISTRADOR', 'MEDICO']);
        if (!$this->horaYaPaso($cita)) {
            Sesion::flash('error', 'No se puede atender una cita antes de su fecha y hora.');
            $this->volverALaAgenda($cita);
        }
        $this->aplicarEstado($cita, 'ATENDIDA', 'Cita ' . $cita['codigo'] . ' marcada como atendida.');
    }

    /** CU17 «extend»: Programada o Confirmada → Inasistencia, cuando la hora ya pasó. */
    public function marcarInasistencia(): void
    {
        $cita = $this->prepararCambio(['PROGRAMADA', 'CONFIRMADA'], ['ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO']);
        if (!$this->horaYaPaso($cita)) {
            Sesion::flash('error', 'No se puede registrar inasistencia antes de la hora de la cita.');
            $this->volverALaAgenda($cita);
        }
        $this->aplicarEstado($cita, 'INASISTENCIA', 'Inasistencia registrada para la cita ' . $cita['codigo'] . '.');
    }

    /**
     * Valida permiso, rol, propiedad de la cita y estado actual antes de cambiarlo.
     * Si algo no cumple, deja el mensaje y vuelve a la agenda.
     */
    private function prepararCambio(array $estadosValidos, array $rolesValidos): array
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $this->exigirRol($rolesValidos);

        $cita = (new Cita())->obtenerParaGestion((int) ($_POST['cita_id'] ?? 0));
        if (!$cita) {
            Sesion::flash('error', 'La cita indicada no existe.');
            $this->redirigir('citas/agenda');
        }

        $usuario = Sesion::usuario();
        if ($usuario['rol'] === 'MEDICO' && (int) $cita['medico_id'] !== (int) $usuario['persona_id']) {
            Sesion::denegar('Intento de modificar la cita ' . (int) $cita['id'] . ' de otro médico');
        }
        if (!in_array($cita['estado'], $estadosValidos, true)) {
            Sesion::flash('error', 'La cita no admite este cambio de estado.');
            $this->volverALaAgenda($cita);
        }
        return $cita;
    }

    /** Ejecuta el cambio en la base y traduce sus rechazos a un mensaje para el usuario. */
    private function aplicarEstado(array $cita, string $nuevoEstado, string $mensaje): void
    {
        try {
            (new Cita())->cambiarEstado((int) $cita['id'], $nuevoEstado, (int) $this->idUsuario());
        } catch (\PDOException $e) {
            Sesion::flash('error', $this->mensajeBaseDatos($e));
            $this->volverALaAgenda($cita);
        }
        Sesion::flash('ok', $mensaje);
        $this->volverALaAgenda($cita);
    }

    private function horaYaPaso(array $cita): bool
    {
        return strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']) <= time();
    }

    private function volverALaAgenda(array $cita): never
    {
        $this->redirigir('citas/agenda&fecha=' . urlencode((string) $cita['fecha']));
    }

    private function exigirRol(array $roles): void
    {
        $rol = (string) (Sesion::usuario()['rol'] ?? '');
        if (!in_array($rol, $roles, true)) {
            Sesion::denegar('Rol sin acceso a la gestión de estados de cita');
        }
    }

    private function mensajeBaseDatos(\PDOException $e): string
    {
        if (preg_match('/ERROR:\s+(.+?)(\n|CONTEXT|$)/s', $e->getMessage(), $coincidencia)) {
            return trim($coincidencia[1]);
        }
        return 'No se pudo cambiar el estado de la cita. Intente nuevamente.';
    }
}
