<?php
namespace App\Servicios;

use App\Nucleo\Config;

/**
 * Servidor de correo externo (CU01, recuperar contraseña). Envía por SMTP con TLS,
 * sin librerías externas. Se configura en .env (o en las variables de Vercel):
 *   MAIL_HOST=smtp.gmail.com  MAIL_PORT=587  MAIL_USER=cuenta@gmail.com
 *   MAIL_PASS=contraseña_de_aplicacion  MAIL_FROM=cuenta@gmail.com  MAIL_FROM_NAME="Clínica Vida Salud"
 * Si MAIL_HOST está vacío no se envía nada y enviar() devuelve false.
 */
final class ServicioCorreo
{
    private $con = null;

    public static function configurado(): bool
    {
        return (string) Config::obtener('MAIL_HOST') !== '';
    }

    public function enviarEnlaceRecuperacion(string $correo, string $nombre, string $enlace, int $minutos): bool
    {
        $html = '<div style="font-family:Arial,sans-serif;color:#3b2f2a;max-width:520px">'
              . '<h2 style="color:#b5573a">Clínica Vida Salud</h2>'
              . '<p>Hola ' . htmlspecialchars($nombre) . ',</p>'
              . '<p>Recibimos una solicitud para restablecer la contraseña de su cuenta. '
              . 'Para crear una contraseña nueva presione el botón:</p>'
              . '<p><a href="' . htmlspecialchars($enlace) . '" style="background:#b5573a;color:#fff;padding:10px 18px;'
              . 'border-radius:8px;text-decoration:none;display:inline-block">Restablecer contraseña</a></p>'
              . '<p>El enlace vence en ' . $minutos . ' minutos y solo puede usarse una vez. '
              . 'Si usted no lo solicitó, ignore este mensaje: su contraseña no cambiará.</p></div>';
        return $this->enviar($correo, 'Restablecer su contraseña - Clínica Vida Salud', $html);
    }

    public function enviar(string $para, string $asunto, string $html): bool
    {
        if (!self::configurado()) {
            return false;
        }
        try {
            $host = (string) Config::obtener('MAIL_HOST');
            $puerto = (int) (Config::obtener('MAIL_PORT') ?: 587);
            $usuario = (string) Config::obtener('MAIL_USER');
            $desde = (string) (Config::obtener('MAIL_FROM') ?: $usuario);
            $nombreDesde = (string) (Config::obtener('MAIL_FROM_NAME') ?: 'Clínica Vida Salud');

            $destino = ($puerto === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $puerto;
            $this->con = @stream_socket_client($destino, $errno, $errstr, 15);
            if (!$this->con) {
                throw new \RuntimeException("No se pudo conectar a $host:$puerto ($errstr)");
            }
            stream_set_timeout($this->con, 15);
            $this->esperar(220);
            $this->orden('EHLO clinica-vida-salud', 250);
            if ($puerto !== 465) {
                $this->orden('STARTTLS', 220);
                if (!stream_socket_enable_crypto($this->con, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('No se pudo iniciar TLS');
                }
                $this->orden('EHLO clinica-vida-salud', 250);
            }
            if ($usuario !== '') {
                $this->orden('AUTH LOGIN', 334);
                $this->orden(base64_encode($usuario), 334);
                $this->orden(base64_encode((string) Config::obtener('MAIL_PASS')), 235);
            }
            $this->orden('MAIL FROM:<' . $desde . '>', 250);
            $this->orden('RCPT TO:<' . $para . '>', 250);
            $this->orden('DATA', 354);
            $cabeceras = [
                'From: =?UTF-8?B?' . base64_encode($nombreDesde) . '?= <' . $desde . '>',
                'To: <' . $para . '>',
                'Subject: =?UTF-8?B?' . base64_encode($asunto) . '?=',
                'Date: ' . date('r'),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $cuerpo = implode("\r\n", $cabeceras) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
            $this->orden($cuerpo . "\r\n.", 250);
            $this->orden('QUIT', 221);
            return true;
        } catch (\Throwable $e) {
            error_log('ServicioCorreo: ' . $e->getMessage());
            return false;
        } finally {
            if ($this->con) {
                fclose($this->con);
                $this->con = null;
            }
        }
    }

    private function orden(string $linea, int $esperado): void
    {
        fwrite($this->con, $linea . "\r\n");
        $this->esperar($esperado);
    }

    private function esperar(int $esperado): void
    {
        $respuesta = '';
        while (($l = fgets($this->con, 515)) !== false) {
            $respuesta .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if ((int) substr($respuesta, 0, 3) !== $esperado) {
            throw new \RuntimeException("SMTP esperaba $esperado y respondió: " . trim($respuesta));
        }
    }
}
