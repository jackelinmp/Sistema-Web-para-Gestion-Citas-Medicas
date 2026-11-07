<?php
namespace App\Servicios;

use App\Nucleo\Config;

/**
 * Envío de códigos OTP por WhatsApp (CU05/CU14 pre-registro).
 * Se configura en .env:
 *   WHATSAPP_PROVIDER=twilio
 *   TWILIO_ACCOUNT_SID=...
 *   TWILIO_AUTH_TOKEN=...
 *   TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
 * Si no está configurado, en modo debug el código se muestra en pantalla.
 */
final class ServicioWhatsApp
{
    public static function configurado(): bool
    {
        return (string) Config::obtener('TWILIO_ACCOUNT_SID') !== ''
            && (string) Config::obtener('TWILIO_AUTH_TOKEN') !== '';
    }

    /** Envía un código OTP. Devuelve true si se envió (o si está en modo debug). */
    public static function enviarOtp(string $telefono, string $codigo): bool
    {
        if (!self::configurado()) {
            // Modo debug: el código se muestra en pantalla (no se envía nada).
            error_log('WhatsApp OTP (debug) para ' . $telefono . ': ' . $codigo);
            return true;
        }
        // Integración con Twilio WhatsApp API (requiere credenciales reales).
        $sid = (string) Config::obtener('TWILIO_ACCOUNT_SID');
        $token = (string) Config::obtener('TWILIO_AUTH_TOKEN');
        $from = (string) (Config::obtener('TWILIO_WHATSAPP_FROM') ?: 'whatsapp:+14155238886');
        $to = 'whatsapp:' . $telefono;
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';
        $data = http_build_query(['From' => $from, 'To' => $to, 'Body' => 'Su codigo de verificacion Clinica Vida Salud es: ' . $codigo]);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Basic " . base64_encode($sid . ':' . $token) . "\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content' => $data,
                'timeout' => 15,
            ],
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        return $resp !== false;
    }
}