<?php
namespace App\Servicios;

use App\Nucleo\Config;

/**
 * Cliente mínimo de Stripe (sandbox) con cURL, sin librerías. Usado por CU19 Procesar Pago en Línea.
 * Variables: STRIPE_SECRET_KEY (sk_test_...), STRIPE_WEBHOOK_SECRET (whsec_...),
 * STRIPE_API_BASE (opcional, solo para pruebas con un servidor simulado).
 * Los datos de la tarjeta los captura Stripe: este sistema nunca los ve ni los guarda.
 */
final class ServicioStripe
{
    public static function configurado(): bool
    {
        return (string) Config::obtener('STRIPE_SECRET_KEY') !== '';
    }

    /** Crea una sesión de Checkout y devuelve ['id' => ..., 'url' => ...]. */
    public function crearSesion(int $citaId, int $centavos, string $moneda, string $descripcion, string $urlExito, string $urlCancelado): array
    {
        return $this->llamar('POST', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $urlExito,
            'cancel_url' => $urlCancelado,
            'client_reference_id' => (string) $citaId,
            'metadata' => ['cita_id' => (string) $citaId],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => strtolower($moneda), 'unit_amount' => $centavos,
                                 'product_data' => ['name' => $descripcion]],
            ]],
        ]);
    }

    public function obtenerSesion(string $id): array
    {
        return $this->llamar('GET', '/v1/checkout/sessions/' . rawurlencode($id));
    }

    /** Valida el encabezado Stripe-Signature (t=...,v1=HMAC-SHA256(secreto, "t.payload")) con tolerancia de 5 minutos. */
    public static function firmaValida(string $payload, string $cabecera, string $secreto, int $tolerancia = 300): bool
    {
        if ($secreto === '' || $cabecera === '') {
            return false;
        }
        $t = null;
        $firmas = [];
        foreach (explode(',', $cabecera) as $parte) {
            [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $firmas[] = $v;
            }
        }
        if (!$t || abs(time() - $t) > $tolerancia) {
            return false;
        }
        $esperada = hash_hmac('sha256', $t . '.' . $payload, $secreto);
        foreach ($firmas as $f) {
            if (hash_equals($esperada, $f)) {
                return true;
            }
        }
        return false;
    }

    private function llamar(string $metodo, string $ruta, array $datos = []): array
    {
        if (!self::configurado()) {
            throw new \RuntimeException('El pago en línea no está configurado (falta STRIPE_SECRET_KEY).');
        }
        $url = rtrim((string) Config::obtener('STRIPE_API_BASE', 'https://api.stripe.com'), '/') . $ruta;
        $ch = curl_init($url);
        $opciones = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERPWD => Config::obtener('STRIPE_SECRET_KEY') . ':'];
        if ($metodo === 'POST') {
            $opciones[CURLOPT_POST] = true;
            $opciones[CURLOPT_POSTFIELDS] = http_build_query($datos);
        }
        curl_setopt_array($ch, $opciones);
        $cuerpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($cuerpo === false) {
            throw new \RuntimeException('No se pudo contactar con Stripe: ' . $error);
        }
        $json = json_decode($cuerpo, true);
        if ($codigo >= 400 || !is_array($json)) {
            throw new \RuntimeException('Stripe rechazó la solicitud' . (isset($json['error']['message']) ? ': ' . $json['error']['message'] : ''));
        }
        return $json;
    }
}
