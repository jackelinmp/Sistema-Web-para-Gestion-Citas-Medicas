<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» FichaClinica — tabla ficha_clinica (composición 1..1 con Paciente). */
final class FichaClinica extends EntidadBase
{
    /** CU05 paso 8: la ficha nace con "No especificado" (valores por defecto de la tabla). */
    public function crearFichaVacia(int $paciente_id): void
    {
        $this->bd->prepare('INSERT INTO ficha_clinica (paciente_id) VALUES (:id) ON CONFLICT (paciente_id) DO NOTHING')
                 ->execute(['id' => $paciente_id]);
    }
}
