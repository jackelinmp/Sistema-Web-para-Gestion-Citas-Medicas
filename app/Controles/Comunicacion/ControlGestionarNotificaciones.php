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
use App\Servicios\ServicioWhatsApp;

/**
 * «control» ControlGestionarNotificaciones — CU24 Notificaciones y recordatorios.
 * Interfaces: IUNotificaciones.  Entidades: Notificacion, Usuario, Persona, Bitacora.
 *
 * Avisos de citas registradas, cambiadas o canceladas, y recordatorio del día anterior.
 * El aviso va a la cuenta de quien maneja las citas: el propio paciente o su padre/tutor (CU22).
 * Medios: en pantalla siempre («Mis notificaciones»); por correo si la clínica configuró MAIL_*;
 * por WhatsApp si el titular lo activó en «Mi perfil» y la clínica configuró WHATSAPP_*.
 * La tarea diaria (ejecutarTareaProgramada) no requiere sesión: la llama un cron con la clave.
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
        $generados = $this->generarRecordatorios(date('Y-m-d', strtotime('+1 day')));
        $enviados = $this->enviarPendientes();
        Sesion::flash('ok', "Se generaron $generados recordatorio(s) para las citas de mañana y se enviaron $enviados aviso(s)."
            . (ServicioCorreo::configurado() || ServicioWhatsApp::configurado() ? '' : ' El correo no está configurado: los avisos se ven en pantalla.'));
        $this->redirigir('notificaciones');
    }

    /**
     * Tarea diaria sin sesión: genera los recordatorios de mañana y envía los avisos pendientes.
     * La llama un cron (cron-job.org o Cron Job de Render) una vez al día, por ejemplo a las 08:00:
     *   ?r=notificaciones/tarea&clave=CRON_CLAVE   o con la cabecera  Authorization: Bearer CRON_CLAVE
     */
    public function ejecutarTareaProgramada(): void
    {
        // Sin clave cualquiera podía disparar correos masivos desde Internet.
        $clave = (string) \App\Nucleo\Config::obtener('CRON_CLAVE');
        $recibida = (string) ($_GET['clave'] ?? '');
        if ($recibida === '' && preg_match('/^Bearer\s+(.+)$/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
            $recibida = trim($m[1]);
        }
        if ($clave === '' || !hash_equals($clave, $recibida)) {
            http_response_code(403);
            exit('Clave incorrecta.');
        }
        $manana = date('Y-m-d', strtotime('+1 day'));
        $generados = $this->generarRecordatorios($manana);
        $enviados = $this->enviarPendientes();
        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Tarea CU24 ejecutada: $generados recordatorio(s) generados, $enviados aviso(s) enviado(s).";
        exit;
    }

    /** Crea un aviso por cada cita activa de la fecha que aún no tenga recordatorio. */
    private function generarRecordatorios(string $fecha): int
    {
        $notificaciones = new Notificacion();
        $total = 0;
        foreach ($notificaciones->listarCitasSinRecordatorio($fecha) as $c) {
            $mensaje = 'Le recordamos la cita de ' . $c['paciente'] . ' el ' . date('d/m/Y', strtotime((string) $c['fecha']))
                     . ' a las ' . substr((string) $c['hora_inicio'], 0, 5) . ' con ' . $c['medico'] . ' (' . $c['especialidad'] . ').'
                     . ' Código: ' . $c['codigo'] . '.';
            if (($c['pago_estado'] ?? '') === 'PENDIENTE') {
                $mensaje .= "\nEl pago está pendiente: puede realizarlo en línea desde «Pagos» o en caja el día de la consulta.";
            }
            $mensaje .= $this->indicacionPrimeraVez((int) $c['id'])
                      . "\nSi no puede asistir, cancele o reprograme desde «Mis citas».";
            $notificaciones->registrar((int) $c['usuario_id'], (int) $c['id'], 'RECORDATORIO', 'Recordatorio de su cita médica', $mensaje);
            $total++;
        }
        return $total;
    }

    /**
     * Envía los avisos pendientes por los medios disponibles: correo (si está configurado) y
     * WhatsApp (si el titular lo activó, la clínica lo configuró y hay celular). Se marca enviado
     * cuando al menos un medio funcionó. Devuelve cuántos avisos salieron.
     */
    private function enviarPendientes(): int
    {
        $hayCorreo = ServicioCorreo::configurado();
        $hayWhatsApp = ServicioWhatsApp::configurado();
        if (!$hayCorreo && !$hayWhatsApp) {
            return 0;   // sin medios externos el aviso igual se ve en pantalla
        }
        $notificaciones = new Notificacion();
        $correo = new ServicioCorreo();
        $whatsapp = new ServicioWhatsApp();
        $enviados = 0;
        foreach ($notificaciones->listarSinEnviar() as $n) {
            $salio = $hayCorreo && $correo->enviarNotificacion($n['correo'], $n['nombre'], $n['asunto'], $n['mensaje']);
            if ($hayWhatsApp && $n['avisos_whatsapp'] && !empty($n['telefono'])) {
                $salio = $whatsapp->enviar((string) $n['telefono'], $n['asunto'] . ': ' . $n['mensaje']) || $salio;
            }
            if ($salio) {
                $notificaciones->marcarEnviada((int) $n['id']);
                $enviados++;
            }
        }
        return $enviados;
    }

    /** Línea extra para quien viene por primera vez (recepción completa sus datos ese día). */
    private function indicacionPrimeraVez(int $citaId): string
    {
        return (new Notificacion())->esPrimeraConsulta($citaId)
            ? "\nPrimera consulta: preséntese en recepción 15 minutos antes con el CI del paciente."
            : '';
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
                if ($tipo === 'CONFIRMACION') {
                    $mensaje .= $this->indicacionPrimeraVez($citaId);
                }
                (new Notificacion())->registrar((int) $uid, $citaId, $tipo, $asunto, $mensaje);
            }
            $this->enviarPendientes();
        } catch (\Throwable $e) {
            error_log('CU24 notificarCita: ' . $e->getMessage());   // un aviso nunca debe romper el caso de uso que lo llama
        }
    }

    /** Procesa los avisos pendientes de envío (correo y WhatsApp). */
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