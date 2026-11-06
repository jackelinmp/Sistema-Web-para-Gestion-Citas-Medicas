<?php
namespace App\Nucleo;

use PDO;

/** Única conexión PDO a PostgreSQL por petición. */
final class Conexion
{
    private static ?PDO $pdo = null;

    public static function obtener(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
                Config::obtener('DB_HOST', 'localhost'), Config::obtener('DB_PORT', '5432'),
                Config::obtener('DB_NAME') ?? Config::obtener('DB_DATABASE'));   // acepta también el .env de Laravel
            if (Config::obtener('DB_SSLMODE')) {
                $dsn .= ';sslmode=' . Config::obtener('DB_SSLMODE');
            }
            self::$pdo = new PDO($dsn, Config::obtener('DB_USER') ?? Config::obtener('DB_USERNAME'),
                                 Config::obtener('DB_PASS') ?? Config::obtener('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET TIME ZONE 'America/La_Paz'");
        }
        return self::$pdo;
    }

    /**
     * Ejecuta $operacion en una transacción atómica (RNF09) e informa a los triggers
     * qué usuario la realiza (app.usuario_id, usado por historial_estado_cita).
     */
    public static function transaccion(callable $operacion): mixed
    {
        $pdo = self::obtener();
        $pdo->beginTransaction();
        try {
            $usuario = Sesion::usuario()['id'] ?? null;
            if ($usuario !== null) {
                $pdo->prepare("SELECT set_config('app.usuario_id', :u, true)")->execute(['u' => (string) $usuario]);
            }
            $resultado = $operacion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
