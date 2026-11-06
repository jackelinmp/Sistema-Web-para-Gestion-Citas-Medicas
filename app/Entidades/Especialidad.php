<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Especialidad — tabla especialidad (CU08). */
final class Especialidad extends EntidadBase
{
    /** Campos del formulario IUFormEspecialidad = columnas de la tabla. */
    public const CAMPOS = ['nombre', 'descripcion', 'costo_consulta', 'edad_minima', 'edad_maxima', 'sexo_permitido'];

    public function listarEspecialidades(): array
    {
        return $this->bd->query('SELECT id, nombre, descripcion, costo_consulta, edad_minima, edad_maxima,
                                        sexo_permitido, estado
                                   FROM especialidad ORDER BY nombre')->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, nombre, descripcion, costo_consulta, edad_minima, edad_maxima,
                                         sexo_permitido, estado
                                    FROM especialidad WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function existeNombre(string $nombre, ?int $excluir_id = null): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM especialidad
                                   WHERE lower(nombre) = lower(:nombre) AND id IS DISTINCT FROM :excluir');
        $st->execute(['nombre' => $nombre, 'excluir' => $excluir_id]);
        return (bool) $st->fetchColumn();
    }

    public function insertar(array $d): int
    {
        $st = $this->bd->prepare('INSERT INTO especialidad (nombre, descripcion, costo_consulta, edad_minima, edad_maxima, sexo_permitido)
                                  VALUES (:nombre, :descripcion, :costo_consulta, :edad_minima, :edad_maxima, :sexo_permitido)
                                  RETURNING id');
        $st->execute($d);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d): void
    {
        $st = $this->bd->prepare('UPDATE especialidad SET nombre = :nombre, descripcion = :descripcion,
                                         costo_consulta = :costo_consulta, edad_minima = :edad_minima,
                                         edad_maxima = :edad_maxima, sexo_permitido = :sexo_permitido
                                   WHERE id = :id');
        $st->execute($d + ['id' => $id]);
    }

    public function cambiarEstado(int $id, bool $estado): void
    {
        $this->bd->prepare('UPDATE especialidad SET estado = :estado WHERE id = :id')
                 ->execute(['estado' => $estado ? 'true' : 'false', 'id' => $id]);
    }

    /** Especialidades activas (CU09, CU13). */
    public function listarActivas(): array
    {
        return $this->bd->query('SELECT id, nombre, costo_consulta FROM especialidad WHERE estado ORDER BY nombre')->fetchAll();
    }

    /** CU06 (incluido por CU14): activas y permitidas para la edad y el sexo del paciente en la fecha de la cita. */
    public function listarPermitidas(int $paciente_id, string $fecha): array
    {
        $st = $this->bd->prepare('SELECT id, nombre, costo_consulta FROM fn_especialidades_permitidas(:paciente, CAST(:fecha AS date))');
        $st->execute(['paciente' => $paciente_id, 'fecha' => $fecha]);
        return $st->fetchAll();
    }
}
