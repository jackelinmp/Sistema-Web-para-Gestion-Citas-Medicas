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
 *   AI_MODEL      = modelo (por defecto: openai/gpt-oss-20b)
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

        $system = "Eres el asistente virtual oficial de la Clínica Vida Salud. Responde en español, de forma breve y amable. "
                . "Solo puedes ayudar con: horarios de atención, citas médicas, reprogramación/cancelación, "
                . "pagos, especialidades y dudas generales de la clínica. Si no sabes algo, di que lo pasas al personal. "
                . "No inventes datos médicos, horarios, precios ni nombres: usa SOLO la base de conocimiento de abajo. "
                . "Para horarios libres de un médico o especialidad indica la pantalla «Disponibilidad horaria»; "
                . "para reservar, «Agendar cita»; para cancelar o reprogramar (hasta 24 horas antes), «Mis citas». "
                . "Contexto de la base de conocimiento:\n";
        foreach ($faqContexto as $f) {
            $system .= "- P: {$f['pregunta']}\n  R: {$f['respuesta']}\n";
        }

        // Intentar con el modelo configurado; si falla (modelo inexistente), usar alternativas.
        $modelos = array_unique([(string) (Config::obtener('AI_MODEL') ?: 'openai/gpt-oss-20b'), 'openai/gpt-oss-20b', 'qwen/qwen3.8-27b']);
        foreach ($modelos as $m) {
            $payload = json_encode([
                'model' => $m,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $mensaje],
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
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
            if ($resp !== false) {
                $data = json_decode($resp, true);
                $texto = $data['choices'][0]['message']['content'] ?? null;
                if (is_string($texto) && trim($texto) !== '') {
                    return trim($texto);
                }
            }
        }
        return null;
    }
}