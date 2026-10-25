<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Jornada — tabla jornada (horario semanal de atención de un médico, CU11). */
final class Jornada extends EntidadBase
{
    /** Jornada vigente indexada por día (1 = lunes ... 7 = domingo). */
    public function listarPorMedico(int $medico_id): array
    {
        $st = $this->bd->prepare('SELECT dia_semana, hora_inicio, hora_fin, duracion_bloque_min
                                    FROM jornada WHERE medico_id = :m AND vigente ORDER BY dia_semana');
        $st->execute(['m' => $medico_id]);
        $porDia = [];
        foreach ($st->fetchAll() as $fila) {
            $porDia[(int) $fila['dia_semana']] = $fila;
        }
        return $porDia;
    }

    /**
     * Guarda la nueva jornada: la anterior deja de estar vigente (se conserva) y se
     * registra una fila por día de atención. La restricción ex_jornada_superpuesta
     * de la base impide horarios superpuestos.
     */
    public function guardar(int $medico_id, array $dias): void
    {
        $this->bd->prepare('UPDATE jornada SET vigente = false WHERE medico_id = :m AND vigente')
                 ->execute(['m' => $medico_id]);
        $ins = $this->bd->prepare('INSERT INTO jornada (medico_id, dia_semana, hora_inicio, hora_fin, duracion_bloque_min)
                                   VALUES (:m, :dia, :ini, :fin, :dur)');
        foreach ($dias as $dia => $d) {
            $ins->execute(['m' => $medico_id, 'dia' => $dia, 'ini' => $d['hora_inicio'],
                           'fin' => $d['hora_fin'], 'dur' => $d['duracion_bloque_min']]);
        }
    }
}
