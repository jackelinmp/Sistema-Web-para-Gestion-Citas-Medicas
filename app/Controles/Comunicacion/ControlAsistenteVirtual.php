<?php
namespace App\Controles\Comunicacion;

use App\Entidades\Cita;
use App\Entidades\Conversacion;
use App\Entidades\PreguntaFrecuente;
use App\Nucleo\Config;
use App\Nucleo\ControlBase;
use App\Servicios\ServicioIA;
use App\Nucleo\Sesion;

/**
 * «control» ControlAsistenteVirtual — CU25 Asistente virtual (reglas, sin API externa).
 * Interfaces: IUAsistente.  Entidades: Conversacion, PreguntaFrecuente, Cita.
 * También atiende visitantes sin sesión. La base de conocimiento es CU26.
 */
final class ControlAsistenteVirtual extends ControlBase
{
    private const MODULO = 'COMUNICACION';

    /** Muestra el chat y conserva (o abre) la conversación en sesión. */
    public function mostrarChat(): void
    {
        $convId = $this->obtenerOCrearConversacion();
        $this->vista('Comunicacion/IUAsistente', [
            'conversacionId' => $convId,
            'mensajes'       => (new Conversacion())->listarMensajes($convId),
            'usuario'        => Sesion::usuario(),
        ]);
    }

    /** Recibe el texto del usuario, responde por reglas (o por n8n si está configurado) y vuelve al chat. */
    public function enviarMensaje(): void
    {
        $convId = (string) ($_POST['conversacion_id'] ?? '');
        if ($convId === '' || !(new Conversacion())->estaActiva($convId)) {
            $convId = $this->obtenerOCrearConversacion();
        }
        $texto = trim((string) ($_POST['texto'] ?? ''));
        if ($texto !== '') {
            (new Conversacion())->registrarMensaje($convId, 'PACIENTE', $texto, null);
            [$intencion, $respuesta] = $this->responder($texto, $convId);
            (new Conversacion())->registrarMensaje($convId, 'ASISTENTE', $respuesta, $intencion);
        }
        $this->redirigir('asistente');
    }

    /** Cierra la conversación activa. */
    public function cerrarConversacion(): void
    {
        $convId = (string) ($_POST['conversacion_id'] ?? '');
        if ($convId !== '') {
            (new Conversacion())->cerrar($convId);
        }
        unset($_SESSION['chat_conversacion']);
        $this->redirigir('asistente');
    }

    /** Abre una conversación nueva si no hay una activa en la sesión. */
    private function obtenerOCrearConversacion(): string
    {
        $existente = $_SESSION['chat_conversacion'] ?? null;
        if (is_string($existente) && (new Conversacion())->estaActiva($existente)) {
            return $existente;
        }
        $usuario = Sesion::usuario();
        $pacienteId = null;
        if ($usuario && ($usuario['rol'] ?? '') === 'PACIENTE') {
            $pacienteId = (int) $usuario['persona_id'];
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $id = (new Conversacion())->iniciar($pacienteId, $ip);
        $_SESSION['chat_conversacion'] = $id;
        return $id;
    }

    /**
     * Responde por reglas: primero busca en la base de conocimiento (CU26),
     * luego reconoce intenciones simples (mis citas, horarios). Si nada aplica,
     * registra la pregunta como SIN_RESPUESTA para que el personal la revise.
     */
    private function responder(string $texto, string $convId): array
    {
        // 1) Intentar con IA (si esta configurada). Se le pasa la base de conocimiento como contexto.
        if (ServicioIA::configurado()) {
            $faqAll = (new PreguntaFrecuente())->listar();
            $contexto = array_map(fn($f) => ['pregunta' => $f['pregunta'], 'respuesta' => $f['respuesta']], $faqAll);
            $respuestaIA = ServicioIA::responder($texto, $contexto);
            if ($respuestaIA !== null) {
                return ['IA', $respuestaIA];
            }
        }
        $faq = (new PreguntaFrecuente())->buscarRespuesta($texto);
        if ($faq) {
            return ['FAQ_' . $faq['id'], (string) $faq['respuesta']];
        }

        $normalizado = PreguntaFrecuente::normalizar($texto);
        $usuario = Sesion::usuario();

        if (str_contains($normalizado, 'mis citas') || str_contains($normalizado, 'mis proximas')) {
            if (!$usuario) {
                return ['SIN_SESION', 'Para ver sus citas debe iniciar sesión.'];
            }
            $citas = (new Cita())->listarProximas((int) $usuario['persona_id'], '');
            if (!$citas) {
                return ['MIS_CITAS', 'No tiene citas próximas registradas.'];
            }
            $lineas = ['Sus próximas citas:'];
            foreach ($citas as $c) {
                $lineas[] = '- ' . $c['codigo'] . ' el ' . $c['fecha'] . ' a las ' . substr((string) $c['hora_inicio'], 0, 5)
                          . ' con ' . $c['medico'] . ' (' . $c['especialidad'] . ').';
            }
            return ['MIS_CITAS', implode("\n", $lineas)];
        }

        if (str_contains($normalizado, 'horario') || str_contains($normalizado, 'hora de atencion')) {
            return ['HORARIOS', 'La atención es de lunes a viernes. Consulte la disponibilidad por especialidad en "Disponibilidad horaria" para ver los cupos libres.'];
        }

        if (str_contains($normalizado, 'cancelar') || str_contains($normalizado, 'reprogramar')) {
            return ['CAMBIOS', 'Puede reprogramar o cancelar sus citas desde "Mis citas", con al menos 24 horas de anticipación.'];
        }

        return ['SIN_RESPUESTA', 'No tengo una respuesta para esa consulta. Se la paso al personal para que la revise.'];
    }

}
