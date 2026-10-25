<?php
namespace App\Nucleo;

/**
 * Traduce ?r=modulo/accion a una operación de un Control.
 * Las rutas están en app/rutas.php: 'ruta' => [Control::class, 'operacion', 'GET'|'POST'[, 'sin_csrf']].
 */
final class Enrutador
{
    public function __construct(private array $rutas)
    {
    }

    public function despachar(string $ruta, string $metodo): void
    {
        $destino = $this->rutas[$ruta] ?? null;
        if ($destino === null) {
            http_response_code(404);
            Vista::mostrar('plantillas/IUNoEncontrado', [], Sesion::usuario() ? 'principal' : 'publica');
            return;
        }
        [$control, $operacion, $verbo] = $destino;
        $sinCsrf = ($destino[3] ?? '') === 'sin_csrf';   // solo el webhook de Stripe: no trae token, se valida por firma
        if ($verbo !== $metodo) {
            http_response_code(405);
            exit('Método no permitido.');
        }
        if ($metodo === 'POST' && !$sinCsrf) {
            Sesion::verificarCsrf();
        }
        (new $control())->$operacion();
    }
}
