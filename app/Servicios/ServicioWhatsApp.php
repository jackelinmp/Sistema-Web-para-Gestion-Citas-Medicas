<?php
namespace App\Servicios;

use App\Nucleo\Config;

/**
 * Servicio externo de mensajería WhatsApp (CU24), con la API oficial WhatsApp Cloud de Meta.
 * Se configura en .env (o en las variables de Render):
 *   WHATSAPP_TOKEN=token_de_acceso   WHATSAPP_PHONE_ID=id_del_numero_de_la_clinica
 *   WHATSAPP_PLANTILLA=aviso_cita    (plantilla aprobada en Meta con un solo parámetro {{1}} en el cuerpo)
 * Meta solo permite que una empresa escriba primero usando una plantilla aprobada; por eso
 * el texto del aviso va como parámetro de la plantilla. Sin token no se envía nada.
 */
final class ServicioWhatsApp
{
    public static function configurado(): bool
    {
        return (string) Config::obtener('WHATSAPP_TOKEN') !== '' && (string) Config::obtener('WHATSAPP_PHONE_ID') !== '';
    }

    /** Número en formato internacional sin «+». Un celular boliviano de 8 dígitos recibe el 591 delante. */
    public static function normalizarNumero(string $telefono): ?string
    {
        $n = preg_replace('/\D/', '', $telefono);
        if (strlen($n) === 8) {
            $n = '591' . $n;
        }
        return strlen($n) >= 10 ? $n : null;
    }

    public function enviar(string $telefono, string $texto): bool
    {
        $numero = self::normalizarNumero($telefono);
        if (!self::configurado() || $numero === null) {
            return false;
        }
        // Los parámetros de plantilla no admiten saltos de línea ni más de 4 espacios seguidos.
        $parametro = trim(preg_replace('/\s+/', ' ', $texto));
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'       => $numero,
            'type'     => 'template',
            'template' => [
                'name'       => (string) (Config::obtener('WHATSAPP_PLANTILLA') ?: 'aviso_cita'),
                'language'   => ['code' => 'es'],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr($parametro, 0, 1000)]]]],
            ],
        ]);
        $url = 'https://graph.facebook.com/v21.0/' . rawurlencode((string) Config::obtener('WHATSAPP_PHONE_ID')) . '/messages';
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => 'Authorization: Bearer ' . Config::obtener('WHATSAPP_TOKEN') . "\r\n"
                                 . "Content-Type: application/json\r\n",
                'content'       => $payload,
                'timeout'       => 8,
                'ignore_errors' => true,
            ],
        ]);
        $respuesta = @file_get_contents($url, false, $ctx);
        $datos = $respuesta === false ? null : json_decode($respuesta, true);
        if (empty($datos['messages'][0]['id'])) {
            error_log('CU24 WhatsApp: ' . ($respuesta === false ? 'sin respuesta' : $respuesta));
            return false;
        }
        return true;
    }

    /** Enlace para que recepción abra el chat a mano (respaldo cuando la API no está configurada). */
    public static function enlaceChat(string $telefono, string $texto): ?string
    {
        $numero = self::normalizarNumero($telefono);
        return $numero === null ? null : 'https://wa.me/' . $numero . '?text=' . rawurlencode($texto);
    }
}
