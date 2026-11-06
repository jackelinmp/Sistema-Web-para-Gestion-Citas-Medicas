<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» ParametroSistema — tabla parametro_sistema (valores configurables sin tocar el código). */
final class ParametroSistema extends EntidadBase
{
    public function obtenerValor(string $clave, string $porDefecto): string
    {
        $st = $this->bd->prepare('SELECT valor FROM parametro_sistema WHERE clave = :c');
        $st->execute(['c' => $clave]);
        return (string) ($st->fetchColumn() ?: $porDefecto);
    }
}
