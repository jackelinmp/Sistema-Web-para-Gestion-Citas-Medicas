<?php
namespace App\Servicios;

use App\Nucleo\Config;

/**
 * Servicio de IA para el asistente virtual (CU25). Llama a una API compatible con OpenAI
 * (Groq, OpenAI, Ollama, etc.). Si no está configurado, devuelve null y se usan las reglas locales.
 *
 * Variables de entorno:
 *   AI_API_KEY    = clave de la API (Groq es gratis: https://console.groq.com)
 *   AI_API_URL    = URL base (por defecto: https://api.groq.com/openai/v1)
 *   AI_MODEL      = modelo (por defecto: llama-3.1-8b-instant)
 */
final class ServicioIA
{
    public static function configurado(): bool
    {
        return (string) Config::obtener('AI_API_KEY') !== '';
    }

    /**
     * Envía el mensaje del usuario (con contexto de FAQ) y devuelve la respuesta de la IA,
     * o null si falla (para caer a las reglas locales).
     */
    public static function responder(string $mensaje, array $faqContexto = [], ?string $intencionLocal = null): ?string
    {
        if (!self::configurado()) {
            return null;
        }
        $url = rtrim((string) (Config::obtener('AI_API_URL') ?: 'https://api.groq.com/openai/v1'), '/') . '/chat/completions';
        $model = (string) (Config::obtener('AI_MODEL') ?: 'llama-3.1-8b-instant');

        $system = "Eres el asistente virtual oficial de la Clínica Vida Salud. Responde en español, de forma breve y amable. "
                . "Solo puedes ayudar con: horarios de atención, citas médicas, reprogramación/cancelación, "
                . "pagos, especialidades y dudas generales de la clínica. Si no sabes algo, di que lo pasas al personal. "
                . "No inventes datos médicos. Información oficial de la clínica: "
                . "Horario general de atención: Lunes a Viernes de 07:00 a 19:00. "
                . "Especialidad Dermatología: atiende Lunes, Miércoles y Viernes de 08:00 a 12:00. "
                . "Especialidad Pediatría: atiende Martes y Jueves de 14:00 a 18:00. "
                . "Especialidad Medicina General: atiende de Lunes a Viernes de 07:00 a 19:00. "
                . "Reservas de citas: se realizan en el módulo Reservas del panel del paciente. "
                . "Cancelaciones: se pueden cancelar citas hasta 24 horas antes desde Mis Citas. "
                . "Contexto de la base de conocimiento:\n";
        foreach ($faqContexto as $f) {
            $system .= "- P: {$f['pregunta']}\n  R: {$f['respuesta']}\n";
        }

        $payload = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $mensaje],
            ],
            'temperature' => 0.4,
            'max_tokens' => 300,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer " . Config::obtener('AI_API_KEY') . "\r\n"
                          . "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) {
            return null;
        }
        $data = json_decode($resp, true);
        $texto = $data['choices'][0]['message']['content'] ?? null;
        return is_string($texto) && trim($texto) !== '' ? trim($texto) : null;
    }
}