<?php
namespace App\Nucleo;

/**
 * Guarda los datos de la sesión en una cookie cifrada (AES-256-GCM) en lugar de un archivo.
 * Se usa en la nube (Vercel), donde cada petición puede ejecutarse en un servidor distinto
 * y los archivos de sesión se pierden. Se activa con SESSION_DRIVER=cookie y necesita APP_KEY.
 */
final class SesionCookie implements \SessionHandlerInterface
{
    private const COOKIE = 'cvs_sesion';
    private string $clave;

    public function __construct(string $appKey)
    {
        if (strlen($appKey) < 32) {
            throw new \RuntimeException('APP_KEY debe tener al menos 32 caracteres para cifrar la sesión.');
        }
        $this->clave = hash('sha256', $appKey, true);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $valor = $_COOKIE[self::COOKIE] ?? '';
        $bin = base64_decode(strtr($valor, '-_', '+/'), true);
        if ($bin === false || strlen($bin) < 29) {
            return '';
        }
        $datos = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', $this->clave, OPENSSL_RAW_DATA,
                                 substr($bin, 0, 12), substr($bin, 12, 16));
        return $datos === false ? '' : $datos;
    }

    public function write(string $id, string $data): bool
    {
        if (headers_sent()) {
            return false;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($data, 'aes-256-gcm', $this->clave, OPENSSL_RAW_DATA, $iv, $tag);
        $valor = rtrim(strtr(base64_encode($iv . $tag . $cifrado), '+/', '-_'), '=');
        $_COOKIE[self::COOKIE] = $valor;
        return setcookie(self::COOKIE, $valor, self::opciones(0));
    }

    public function destroy(string $id): bool
    {
        unset($_COOKIE[self::COOKIE]);
        return headers_sent() ? false : setcookie(self::COOKIE, '', self::opciones(time() - 3600));
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }

    private static function opciones(int $expira): array
    {
        return ['expires' => $expira, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                'secure' => Sesion::esHttps()];
    }
}
