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
    /** CU10: al dar de baja al médico su jornada deja de estar vigente (se conserva como historial). */
    public function cerrarVigente(int $medico_id): void
    {
        $this->bd->prepare('UPDATE jornada SET vigente = false WHERE medico_id = :m AND vigente')
                 ->execute(['m' => $medico_id]);
    }

    /**
     * CU18: carga horaria por médico en un periodo.
     * horas_jornada = horas de atención que su jornada semanal le asigna en esos días;
     * horas_citas = horas ocupadas por citas no canceladas; ocupación = citas ÷ jornada;
     * dias_ausente = días de jornada cubiertos por un bloqueo de agenda (vacaciones, permiso, reunión: CU12).
     */
    public function reportarCargaHoraria(string $desde, string $hasta): array
    {
        $st = $this->bd->prepare("WITH dias AS (
                                      SELECT CAST(d AS date) AS dia FROM generate_series(CAST(:d1 AS date), CAST(:h1 AS date), interval '1 day') d
                                  ), disponible AS (
                                      SELECT j.medico_id,
                                             sum(extract(epoch FROM j.hora_fin - j.hora_inicio)) / 3600 AS horas_jornada,
                                             count(DISTINCT dias.dia) FILTER (WHERE EXISTS (
                                                 SELECT 1 FROM bloqueo_agenda b WHERE b.medico_id = j.medico_id
                                                    AND b.fecha_inicio < dias.dia + 1 AND b.fecha_fin > dias.dia)) AS dias_ausente
                                        FROM jornada j
                                        JOIN dias ON extract(isodow FROM dias.dia) = j.dia_semana
                                       WHERE j.vigente
                                       GROUP BY j.medico_id
                                  ), ocupado AS (
                                      SELECT medico_id, count(*) AS citas,
                                             sum(extract(epoch FROM hora_fin - hora_inicio)) / 3600 AS horas_citas
                                        FROM cita
                                       WHERE fecha BETWEEN CAST(:d2 AS date) AND CAST(:h2 AS date) AND estado <> 'CANCELADA'
                                       GROUP BY medico_id
                                  )
                                  SELECT p.nombres || ' ' || p.apellidos AS medico,
                                         (SELECT string_agg(e.nombre, ', ' ORDER BY e.nombre) FROM medico_especialidad me
                                            JOIN especialidad e ON e.id = me.especialidad_id WHERE me.medico_id = m.persona_id) AS especialidades,
                                         round(COALESCE(d.horas_jornada, 0), 1) AS horas_jornada,
                                         COALESCE(o.citas, 0) AS citas,
                                         round(COALESCE(o.horas_citas, 0), 1) AS horas_citas,
                                         round(100 * COALESCE(o.horas_citas, 0) / NULLIF(d.horas_jornada, 0), 1) AS pct_ocupacion,
                                         COALESCE(d.dias_ausente, 0) AS dias_ausente
                                    FROM medico m
                                    JOIN persona p ON p.id = m.persona_id
                                    LEFT JOIN disponible d ON d.medico_id = m.persona_id
                                    LEFT JOIN ocupado o ON o.medico_id = m.persona_id
                                   WHERE m.estado = 'ACTIVO'
                                   ORDER BY pct_ocupacion DESC NULLS LAST, medico");
        $st->execute(['d1' => $desde, 'h1' => $hasta, 'd2' => $desde, 'h2' => $hasta]);
        return $st->fetchAll();
    }
}
