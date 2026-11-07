<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» VinculoFamiliar — tabla vinculo_familiar (CU22).
 * paciente_id = titular (responsable); familiar_id = paciente a su cargo.
 * La base impide el vínculo consigo mismo (ck_vinculo_distintos) y los duplicados (uq_vinculo).
 */
final class VinculoFamiliar extends EntidadBase
{
    /** Valores del CHECK de la columna parentesco. */
    public const PARENTESCOS = ['HIJO' => 'Hijo(a)', 'PADRE' => 'Padre', 'MADRE' => 'Madre',
                                'CONYUGE' => 'Cónyuge', 'HERMANO' => 'Hermano(a)', 'OTRO' => 'Otro'];

    /** Familiares a cargo del titular. */
    public function listarFamiliares(int $titular_id): array
    {
        $st = $this->bd->prepare("SELECT v.id, v.familiar_id, v.parentesco, v.fecha_vinculo,
                                         pe.nombres || ' ' || pe.apellidos AS nombre, pe.ci, pe.fecha_nacimiento,
                                         pa.nro_expediente, pa.estado
                                    FROM vinculo_familiar v
                                    JOIN paciente pa ON pa.persona_id = v.familiar_id
                                    JOIN persona pe  ON pe.id = v.familiar_id
                                   WHERE v.paciente_id = :t
                                   ORDER BY pe.apellidos, pe.nombres");
        $st->execute(['t' => $titular_id]);
        return $st->fetchAll();
    }

    /** Titulares de los que depende un paciente (un niño puede estar a cargo de su madre y de su padre). */
    public function listarTitulares(int $familiar_id): array
    {
        $st = $this->bd->prepare("SELECT v.paciente_id AS titular_id, v.parentesco, pe.nombres || ' ' || pe.apellidos AS nombre
                                    FROM vinculo_familiar v JOIN persona pe ON pe.id = v.paciente_id
                                   WHERE v.familiar_id = :f ORDER BY pe.apellidos");
        $st->execute(['f' => $familiar_id]);
        return $st->fetchAll();
    }

    public function esFamiliar(int $titular_id, int $familiar_id): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM vinculo_familiar WHERE paciente_id = :t AND familiar_id = :f');
        $st->execute(['t' => $titular_id, 'f' => $familiar_id]);
        return (bool) $st->fetchColumn();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, paciente_id AS titular_id, familiar_id, parentesco FROM vinculo_familiar WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function insertar(int $titular_id, int $familiar_id, string $parentesco): int
    {
        $st = $this->bd->prepare('INSERT INTO vinculo_familiar (paciente_id, familiar_id, parentesco)
                                  VALUES (:t, :f, :p) RETURNING id');
        $st->execute(['t' => $titular_id, 'f' => $familiar_id, 'p' => $parentesco]);
        return (int) $st->fetchColumn();
    }

    public function eliminar(int $id): void
    {
        $this->bd->prepare('DELETE FROM vinculo_familiar WHERE id = :id')->execute(['id' => $id]);
    }
}
