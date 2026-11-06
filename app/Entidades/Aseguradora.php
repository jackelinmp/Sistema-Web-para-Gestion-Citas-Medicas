<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Aseguradora — tabla aseguradora (seguro informativo del paciente, CU05). */
final class Aseguradora extends EntidadBase
{
    public function listarAseguradoras(): array
    {
        return $this->bd->query('SELECT id, nombre FROM aseguradora WHERE estado ORDER BY nombre')->fetchAll();
    }
}
