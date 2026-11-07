<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» BloqueoAgenda — tabla bloqueo_agenda (CU12).
 * Periodo [fecha_inicio, fecha_fin) en que el médico no atiende. fn_cupos_disponibles (CU13) ya los descuenta.
 * La tabla no tiene estado: quitar un bloqueo = eliminarlo (queda constancia en la bitácora).
 */
final class BloqueoAgenda extends EntidadBase
{
    /** Valores del CHECK de la columna tipo_bloqueo. */
    public const TIPOS = ['VACACIONES' => 'Vacaciones', 'PERMISO' => 'Permiso', 'REUNION' => 'Reunión', 'OTRO' => 'Otro'];

    private const SELECT = "SELECT b.id, b.medico_id, b.fecha_inicio, b.fecha_fin, b.tipo_bloqueo, b.motivo,
                                   p.nombres || ' ' || p.apellidos AS medico
                              FROM bloqueo_agenda b JOIN persona p ON p.id = b.medico_id";

    /** Bloqueos que aún no terminaron (o todos desde $desde), de un médico o de todos. */
    public function listarBloqueos(?int $medico_id, string $desde): array
    {
        $st = $this->bd->prepare(self::SELECT . '
             WHERE (CAST(:m1 AS bigint) IS NULL OR b.medico_id = :m2)
               AND b.fecha_fin > CAST(:desde AS timestamp)
             ORDER BY b.fecha_inicio');
        $st->execute(['m1' => $medico_id, 'm2' => $medico_id, 'desde' => $desde]);
        return $st->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare(self::SELECT . ' WHERE b.id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /** ¿Ya hay un bloqueo del médico que se cruza con este periodo? */
    public function existeSuperpuesto(int $medico_id, string $inicio, string $fin): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM bloqueo_agenda
                                   WHERE medico_id = :m
                                     AND tsrange(fecha_inicio, fecha_fin) && tsrange(CAST(:i AS timestamp), CAST(:f AS timestamp))');
        $st->execute(['m' => $medico_id, 'i' => $inicio, 'f' => $fin]);
        return (bool) $st->fetchColumn();
    }

    public function insertar(array $d): int
    {
        $st = $this->bd->prepare('INSERT INTO bloqueo_agenda (medico_id, fecha_inicio, fecha_fin, tipo_bloqueo, motivo)
                                  VALUES (:medico_id, :fecha_inicio, :fecha_fin, :tipo_bloqueo, :motivo) RETURNING id');
        $st->execute(array_intersect_key($d, array_flip(['medico_id', 'fecha_inicio', 'fecha_fin', 'tipo_bloqueo', 'motivo'])));
        return (int) $st->fetchColumn();
    }

    public function eliminar(int $id): void
    {
        $this->bd->prepare('DELETE FROM bloqueo_agenda WHERE id = :id')->execute(['id' => $id]);
    }
}
