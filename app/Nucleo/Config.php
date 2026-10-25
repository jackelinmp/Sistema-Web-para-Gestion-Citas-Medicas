<?php
namespace App\Nucleo;

/** Lee el archivo .env de la raíz (nunca se sube al repositorio). */
final class Config
{
    private static array $valores = [];

    public static function cargar(string $archivo): void
    {
        if (!is_file($archivo)) {
            // En la nube no hay .env: los valores llegan como variables de entorno del servidor.
            if (getenv('DB_HOST') !== false) {
                return;
            }
            throw new \RuntimeException('Falta el archivo .env (copie .env.example y complételo).');
        }
        foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            self::$valores[$clave] = trim($valor, "\"'");
        }
    }

    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        return self::$valores[$clave] ?? getenv($clave) ?: $porDefecto;
    }
}
