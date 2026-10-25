<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Persona — tabla persona (superclase de Paciente y Medico; dueña de la cuenta Usuario). */
final class Persona extends EntidadBase
{
    /** Columnas que se llenan desde los formularios (mismos nombres que la tabla). */
    public const CAMPOS = ['ci', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'direccion'];

    public function existeCi(string $ci, ?int $excluir_id = null): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM persona WHERE ci = :ci AND id IS DISTINCT FROM :excluir');
        $st->execute(['ci' => $ci, 'excluir' => $excluir_id]);
        return (bool) $st->fetchColumn();
    }

    public function insertar(array $d): int
    {
        $st = $this->bd->prepare('INSERT INTO persona (ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, direccion)
                                  VALUES (:ci, :nombres, :apellidos, :fecha_nacimiento, :sexo, :telefono, :direccion)
                                  RETURNING id');
        $st->execute(array_intersect_key($d, array_flip(self::CAMPOS)));
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d): void
    {
        $st = $this->bd->prepare('UPDATE persona SET ci = :ci, nombres = :nombres, apellidos = :apellidos,
                                         fecha_nacimiento = :fecha_nacimiento, sexo = :sexo,
                                         telefono = :telefono, direccion = :direccion
                                   WHERE id = :id');
        $st->execute(array_intersect_key($d, array_flip(self::CAMPOS)) + ['id' => $id]);
    }
}
