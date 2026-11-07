<?php
namespace App\Nucleo;

/** Lee el archivo .env de la raiz (nunca se sube al repositorio). */
final class Config
{
    private static array $valores = [];

    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            if (getenv('DB_HOST') === false && !isset($_SERVER['DB_HOST']) && !isset($_ENV['DB_HOST'])
                && getenv('DATABASE_URL') === false && !isset($_SERVER['DATABASE_URL']) && !isset($_ENV['DATABASE_URL'])) {
                throw new \RuntimeException('Falta el archivo .env (copie .env.example y completelo).');
            }
        } else {
            foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
                $linea = trim($linea);
                if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                    continue;
                }
                [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
                self::$valores[$clave] = trim($valor, "\"'");
            }
        }
        self::completarDesdeDatabaseUrl();
    }

    private static function completarDesdeDatabaseUrl(): void
    {
        if (self::obtener('DB_HOST')) {
            return;
        }
        $url = self::obtener('DATABASE_URL') ?: self::obtener('DIRECT_URL');
        if (!$url) {
            return;
        }
        $partes = parse_url($url);
        if (!$partes || empty($partes['host'])) {
            return;
        }
        self::$valores['DB_HOST'] = $partes['host'];
        if (!empty($partes['port'])) {
            self::$valores['DB_PORT'] = (string) $partes['port'];
        }
        if (!empty($partes['path'])) {
            self::$valores['DB_NAME'] = ltrim($partes['path'], '/');
        }
        if (!empty($partes['user'])) {
            self::$valores['DB_USER'] = urldecode($partes['user']);
        }
        if (isset($partes['pass'])) {
            self::$valores['DB_PASS'] = urldecode($partes['pass']);
        }
        if (empty(self::$valores['DB_SSLMODE'] ?? null)) {
            self::$valores['DB_SSLMODE'] = 'require';
        }
    }

    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $v = self::$valores[$clave] ?? getenv($clave);
        if ($v === false || $v === null || $v === '') {
            $v = $_SERVER[$clave] ?? $_ENV[$clave] ?? null;
        }
        return ($v === false || $v === null || $v === '') ? $porDefecto : (string) $v;
    }
}