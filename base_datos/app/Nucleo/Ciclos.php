<?php
namespace App\Nucleo;

/**
 * Lee config/ciclos.php y calcula, para el usuario con sesión, el ESTADO de cada ítem:
 *   'ok'    -> disponible (el rol tiene el permiso RBAC)
 *   'rol'   -> bloqueado: el rol del usuario no tiene permiso
 *   'ciclo' -> bloqueado: el ciclo aún no está abierto (Ciclo 2)
 *   'dev'   -> bloqueado: el caso de uso todavía no está implementado
 */
final class Ciclos
{
    /** @return array<int,array> ciclos con sus paquetes e ítems (cada ítem trae 'estado' y 'ciclo'). */
    public static function evaluar(): array
    {
        $ciclos = require RAIZ . '/config/ciclos.php';
        foreach ($ciclos as $n => &$ciclo) {
            $ciclo['numero'] = $n;
            $ciclo['total'] = $ciclo['disponibles'] = 0;
            foreach ($ciclo['paquetes'] as &$paquete) {
                foreach ($paquete['items'] as &$item) {
                    $item['ciclo'] = $n;
                    $item['paquete'] = $paquete['id'];
                    $item['estado'] = self::estado($ciclo, $item);
                    $ciclo['total']++;
                    $ciclo['disponibles'] += $item['estado'] === 'ok' ? 1 : 0;
                }
                unset($item);
            }
            unset($paquete);
        }
        return $ciclos;
    }

    private static function estado(array $ciclo, array $item): string
    {
        if (empty($ciclo['abierto'])) {
            return 'ciclo';
        }
        if (empty($item['listo']) || empty($item['ruta'])) {
            return 'dev';
        }
        if ($item['modulo'] && !Sesion::puede($item['modulo'], $item['accion'])) {
            return 'rol';
        }
        return 'ok';
    }

    /** Ítem (caso de uso) al que pertenece la ruta actual, para mostrar su etiqueta CU en la barra. */
    public static function itemActual(array $ciclos, string $ruta): ?array
    {
        foreach ($ciclos as $ciclo) {
            foreach ($ciclo['paquetes'] as $paquete) {
                foreach ($paquete['items'] as $item) {
                    foreach ($item['rutas'] as $prefijo) {
                        if ($ruta === $prefijo || str_starts_with($ruta, $prefijo . '/')) {
                            return $item;
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function motivo(string $estado): string
    {
        return match ($estado) {
            'rol'   => 'Tu rol no tiene acceso a este caso de uso',
            'ciclo' => 'Disponible en el Ciclo 2',
            'dev'   => 'En desarrollo',
            default => '',
        };
    }
}
