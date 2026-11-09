<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» TipoExamen — tabla tipo_examen: catálogo de exámenes con su costo (CU23). */
final class TipoExamen extends EntidadBase
{
    public function listarActivos(): array
    {
        return $this->bd->query('SELECT id, nombre, descripcion, costo FROM tipo_examen WHERE estado ORDER BY nombre')->fetchAll();
    }
}
