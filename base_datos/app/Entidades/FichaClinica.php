<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» FichaClinica — tabla ficha_clinica (composición 1..1 con Paciente). CU05, CU07, CU21.
 * fecha_actualizacion la pone el trigger tg_ficha_fecha; quién la modificó queda en la bitácora.
 */
final class FichaClinica extends EntidadBase
{
    /** Campos del formulario IUFichaClinica = columnas de la tabla. */
    public const CAMPOS = ['tipo_sangre', 'alergias', 'enfermedades_cronicas', 'antecedentes_familiares', 'antecedentes_quirurgicos'];

    /** Valores del CHECK de tipo_sangre. */
    public const TIPOS_SANGRE = ['No especificado', 'O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

    /** CU05 paso 8: la ficha nace con "No especificado" (valores por defecto de la tabla). */
    public function crearFichaVacia(int $paciente_id): void
    {
        $this->bd->prepare('INSERT INTO ficha_clinica (paciente_id) VALUES (:id) ON CONFLICT (paciente_id) DO NOTHING')
                 ->execute(['id' => $paciente_id]);
    }

    public function obtenerPorPaciente(int $paciente_id): ?array
    {
        $st = $this->bd->prepare('SELECT paciente_id, tipo_sangre, alergias, enfermedades_cronicas, antecedentes_familiares,
                                         antecedentes_quirurgicos, fecha_actualizacion
                                    FROM ficha_clinica WHERE paciente_id = :id');
        $st->execute(['id' => $paciente_id]);
        return $st->fetch() ?: null;
    }

    public function actualizar(int $paciente_id, array $d): void
    {
        $st = $this->bd->prepare('UPDATE ficha_clinica SET tipo_sangre = :tipo_sangre, alergias = :alergias,
                                         enfermedades_cronicas = :enfermedades_cronicas,
                                         antecedentes_familiares = :antecedentes_familiares,
                                         antecedentes_quirurgicos = :antecedentes_quirurgicos
                                   WHERE paciente_id = :id');
        $st->execute(array_intersect_key($d, array_flip(self::CAMPOS)) + ['id' => $paciente_id]);
    }
}