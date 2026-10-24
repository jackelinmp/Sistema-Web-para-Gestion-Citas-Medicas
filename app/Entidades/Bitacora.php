<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Bitacora — tabla bitacora (solo inserción, RN14). */
final class Bitacora extends EntidadBase
{
    public function registrarEvento(?int $usuario_id, string $accion, string $descripcion, ?string $ip,
                                    ?string $tabla = null, ?int $registro_id = null): void
    {
        $this->bd->prepare('SELECT fn_registrar_bitacora(:usuario, :accion, :descripcion, CAST(:ip AS inet), :tabla, :registro)')
                 ->execute(['usuario' => $usuario_id, 'accion' => $accion, 'descripcion' => $descripcion,
                            'ip' => $ip, 'tabla' => $tabla, 'registro' => $registro_id]);
    }
}
