<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Medico — tablas medico y medico_especialidad (subclase de Persona). CU09, CU11, CU13, CU14. */
final class Medico extends EntidadBase
{
    /** CU09: lista con nombre, matrícula, especialidades y estado; filtro por nombre y especialidad. */
    public function listarMedicos(string $criterio = '', ?int $especialidad_id = null): array
    {
        $like = '%' . $criterio . '%';
        $st = $this->bd->prepare("SELECT m.persona_id, m.matricula, m.estado, p.ci, p.telefono,
                                         p.nombres || ' ' || p.apellidos AS nombre, u.correo,
                                         string_agg(e.nombre, ', ' ORDER BY e.nombre) AS especialidades
                                    FROM medico m
                                    JOIN persona p ON p.id = m.persona_id
                                    LEFT JOIN usuario u ON u.persona_id = m.persona_id
                                    LEFT JOIN medico_especialidad me ON me.medico_id = m.persona_id
                                    LEFT JOIN especialidad e ON e.id = me.especialidad_id
                                   WHERE (CAST(:c AS text) = '' OR (p.nombres || ' ' || p.apellidos) ILIKE :l1 OR m.matricula ILIKE :l2)
                                     AND (CAST(:e1 AS smallint) IS NULL OR EXISTS (SELECT 1 FROM medico_especialidad x
                                                                   WHERE x.medico_id = m.persona_id AND x.especialidad_id = :e2))
                                   GROUP BY m.persona_id, m.matricula, m.estado, p.ci, p.telefono, p.nombres, p.apellidos, u.correo
                                   ORDER BY p.apellidos, p.nombres");
        $st->execute(['c' => $criterio, 'l1' => $like, 'l2' => $like, 'e1' => $especialidad_id, 'e2' => $especialidad_id]);
        return $st->fetchAll();
    }

    public function obtenerPorId(int $persona_id): ?array
    {
        $st = $this->bd->prepare("SELECT m.persona_id, m.matricula, m.estado, p.ci, p.nombres, p.apellidos,
                                         p.nombres || ' ' || p.apellidos AS nombre, p.fecha_nacimiento, p.sexo,
                                         p.telefono, p.direccion, u.id AS usuario_id, u.correo
                                    FROM medico m
                                    JOIN persona p ON p.id = m.persona_id
                                    LEFT JOIN usuario u ON u.persona_id = m.persona_id
                                   WHERE m.persona_id = :id");
        $st->execute(['id' => $persona_id]);
        $medico = $st->fetch();
        if (!$medico) {
            return null;
        }
        $medico['especialidades'] = $this->especialidadesDe($persona_id);
        return $medico;
    }

    /** Ids de las especialidades que ejerce el médico. */
    public function especialidadesDe(int $persona_id): array
    {
        $st = $this->bd->prepare('SELECT especialidad_id FROM medico_especialidad WHERE medico_id = :id');
        $st->execute(['id' => $persona_id]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function existeMatricula(string $matricula, ?int $excluir_id = null): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM medico WHERE lower(matricula) = lower(:m) AND persona_id IS DISTINCT FROM :excluir');
        $st->execute(['m' => $matricula, 'excluir' => $excluir_id]);
        return (bool) $st->fetchColumn();
    }

    public function insertar(int $persona_id, string $matricula): void
    {
        $this->bd->prepare('INSERT INTO medico (persona_id, matricula) VALUES (:id, :m)')
                 ->execute(['id' => $persona_id, 'm' => $matricula]);
    }

    public function actualizar(int $persona_id, string $matricula): void
    {
        $this->bd->prepare('UPDATE medico SET matricula = :m WHERE persona_id = :id')
                 ->execute(['m' => $matricula, 'id' => $persona_id]);
    }

    /**
     * Reemplaza las especialidades del médico. Solo quita las que no tienen citas
     * (la FK de cita hacia medico_especialidad impide borrar las usadas).
     */
    public function asignarEspecialidades(int $persona_id, array $especialidades): void
    {
        $this->bd->prepare('DELETE FROM medico_especialidad me
                             WHERE me.medico_id = :id AND NOT (me.especialidad_id = ANY (CAST(:lista AS smallint[])))
                               AND NOT EXISTS (SELECT 1 FROM cita c WHERE c.medico_id = me.medico_id
                                                                     AND c.especialidad_id = me.especialidad_id)')
                 ->execute(['id' => $persona_id, 'lista' => '{' . implode(',', array_map('intval', $especialidades)) . '}']);
        $ins = $this->bd->prepare('INSERT INTO medico_especialidad (medico_id, especialidad_id) VALUES (:id, :esp)
                                   ON CONFLICT DO NOTHING');
        foreach ($especialidades as $esp) {
            $ins->execute(['id' => $persona_id, 'esp' => (int) $esp]);
        }
    }

    /** CU11: médicos activos para configurar su jornada. */
    public function listarActivos(): array
    {
        return $this->bd->query("SELECT m.persona_id, p.nombres || ' ' || p.apellidos AS nombre
                                   FROM medico m JOIN persona p ON p.id = m.persona_id
                                  WHERE m.estado = 'ACTIVO' ORDER BY p.apellidos, p.nombres")->fetchAll();
    }

    /** CU13: médicos activos que ejercen una especialidad. */
    public function listarPorEspecialidad(int $especialidad_id): array
    {
        $st = $this->bd->prepare("SELECT m.persona_id, p.nombres || ' ' || p.apellidos AS nombre
                                    FROM medico m
                                    JOIN persona p ON p.id = m.persona_id
                                    JOIN medico_especialidad me ON me.medico_id = m.persona_id
                                   WHERE me.especialidad_id = :esp AND m.estado = 'ACTIVO'
                                   ORDER BY p.apellidos, p.nombres");
        $st->execute(['esp' => $especialidad_id]);
        return $st->fetchAll();
    }
}
