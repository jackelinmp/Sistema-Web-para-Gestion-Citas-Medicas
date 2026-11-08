<?php
namespace App\Controles\Comunicacion;

use App\Entidades\Bitacora;
use App\Entidades\Notificacion;
use App\Entidades\Persona;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;
use App\Servicios\ServicioCorreo;

/**
 * «control» ControlGestionarNotificaciones — CU24 Notificaciones y recordatorios.
 * Interfaces: IUNotificaciones.  Entidades: Notificacion, Usuario, Persona, Bitacora.
 *
 * Avisos por correo y en pantalla de citas registradas, cambiadas o canceladas, y
 * recordatorio del día anterior. La tarea diaria (ejecutarTareaProgramada) no requiere sesión.
 */
final class ControlGestionarNotificaciones extends ControlBase
{
    private const MODULO = 'COMUNICACION';
    private const ROLES_STAFF = ['ADMINISTRADOR', 'RECEPCIONISTA'];

    /** Bandeja del usuario actual y, para el personal, los avisos recientes. */
    public function listarNotificaciones(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $usuario = Sesion::usuario();
        $esStaff = in_array($usuario['rol'] ?? '', self::ROLES_STAFF, true);
        $this->vista('Comunicacion/IUNotificaciones', [
            'notificaciones' => (new Notificacion())->listarPorUsuario((int) $usuario['id']),
            'noLeidas'       => (new Notificacion())->contarNoLeidas((int) $usuario['id']),
            'recientes'      => $esStaff ? (new Notificacion())->listarRecientes() : [],
            'sinEnviar'      => $esStaff ? (new Notificacion())->contarSinEnviar() : 0,
            'esStaff'        => $esStaff,
        ]);
    }

    /** Marca un aviso como leído (solo el dueño). */
    public function marcarLeida(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        (new Notificacion())->marcarLeida((int) ($_POST['id'] ?? 0), (int) Sesion::usuario()['id']);
        $this->redirigir('notificaciones');
    }

    /** El personal dispara el envío de recordatorios del día siguiente. */
    public function enviarRecordatorios(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $this->exigirRol(self::ROLES_STAFF);
        $enviados = $this->generarRecordatorios(date('Y-m-d', strtotime('+1 day')));
        Sesion::flash('ok', "Se generaron $enviados recordatorio(s) para las citas de mañana.");
        $this->redirigir('notificaciones');
    }

    /** Tarea diaria sin sesión: genera recordatorios y envía los correos pendientes. */
    public function ejecutarTareaProgramada(): void
    {
        // Sin clave cualquiera podía disparar correos masivos desde Internet.
        $clave = (string) \App\Nucleo\Config::obtener('CRON_CLAVE');
        if ($clave === '' || !hash_equals($clave, (string) ($_GET['clave'] ?? ''))) {
            http_response_code(403);
            exit('Clave incorrecta.');
        }
        $manana = date('Y-m-d', strtotime('+1 day'));
        $generados = $this->generarRecordatorios($manana);
        $enviados = $this->enviarPendientes();
        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Tarea CU24 ejecutada: $generados recordatorio(s) generados, $enviados correo(s) enviado(s).";
        exit;
    }

    /** Crea un aviso por cada cita activa de la fecha que aún no tenga recordatorio. */
    private function generarRecordatorios(string $fecha): int
    {
        $citas = (new Notificacion())->listarCitasSinRecordatorio($fecha);
        $total = 0;
        foreach ($citas as $c) {
            $asunto = 'Recordatorio de su cita médica';
            $mensaje = "Le recordamos su cita del {$c['fecha']} a las " . substr((string) $c['hora_inicio'], 0, 5)
                     . " con {$c['medico']} ({$c['especialidad']}). Código: {$c['codigo']}."
                     . " Paciente: {$c['paciente']}.";
            (new Notificacion())->registrar((int) $c['usuario_id'], (int) $c['id'], 'RECORDATORIO', $asunto, $mensaje);
            $total++;
        }
        return $total;
    }

    /** Envía por correo los avisos que todavía no se han enviado. */
    private function enviarPendientes(): int
    {
        $pendientes = (new Notificacion())->listarSinEnviar();
        $correo = new ServicioCorreo();
        $enviados = 0;
        foreach ($pendientes as $n) {
            if ($correo->enviarNotificacion($n['correo'], $n['nombre'], $n['asunto'], $n['mensaje'])) {
                (new Notificacion())->marcarEnviada((int) $n['id']);
                $enviados++;
            }
        }
        return $enviados;
    }

    /** Crea un aviso para el paciente de una cita (si tiene cuenta en el portal). */
    public function notificarCita(int $citaId, string $tipo, string $asunto, string $mensaje): void
    {
        $bd = Conexion::obtener();
        // Destinatario: la cuenta del paciente o, si es un menor sin cuenta, la de su tutor (CU22).
        $st = $bd->prepare("SELECT COALESCE(u.id, ut.id, utv.id)
                              FROM cita c
                              JOIN persona pp ON pp.id = c.paciente_id
                              LEFT JOIN usuario u  ON u.persona_id = c.paciente_id AND u.estado = 'HABILITADA'
                              LEFT JOIN usuario ut ON ut.persona_id = pp.tutor_id AND ut.estado = 'HABILITADA'
                              LEFT JOIN LATERAL (
                                  SELECT us.id
                                    FROM vinculo_familiar vf
                                    JOIN usuario us ON us.persona_id = vf.paciente_id AND us.estado = 'HABILITADA'
                                   WHERE vf.familiar_id = c.paciente_id
                                   ORDER BY (vf.paciente_id = pp.tutor_id) DESC, us.id
                                   LIMIT 1
                              ) utv ON TRUE
                             WHERE c.id = :c");
        $st->execute(['c' => $citaId]);
        $uid = $st->fetchColumn();
        if (!in_array($tipo, ['RECORDATORIO', 'CONFIRMACION', 'CANCELACION', 'SISTEMA'], true)) {
            $tipo = 'SISTEMA';   // notificacion_tipo_check solo admite estos cuatro
        }
        try {
            if ($uid) {
                (new Notificacion())->registrar((int) $uid, $citaId, $tipo, $asunto, $mensaje);
            }
            $this->enviarPendientes();
        } catch (\Throwable $e) {
            error_log('CU24 notificarCita: ' . $e->getMessage());   // un aviso nunca debe romper el caso de uso que lo llama
        }
    }

    /** Procesa los avisos pendientes de envío por correo. */
    public function notificarEventos(): void
    {
        $this->enviarPendientes();
    }

    private function exigirRol(array $roles): void
    {
        $rol = (string) (Sesion::usuario()['rol'] ?? '');
        if (!in_array($rol, $roles, true)) {
            Sesion::denegar('Rol sin acceso a las notificaciones');
        }
    }
}