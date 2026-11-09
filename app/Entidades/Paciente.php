<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Paciente — tabla paciente (subclase de Persona, PK = persona_id). CU05, CU14. */
final class Paciente extends EntidadBase
{
    public const CAMPOS = ['contacto_emergencia_nombre', 'contacto_emergencia_parentesco',
                           'contacto_emergencia_telefono', 'aseguradora_id', 'nro_poliza'];

    private const SELECT = 'SELECT p.persona_id, p.nro_expediente, p.contacto_emergencia_nombre,
                                   p.contacto_emergencia_parentesco, p.contacto_emergencia_telefono,
                                   p.aseguradora_id, p.nro_poliza, p.estado,
                                   pe.ci, pe.nombres, pe.apellidos, pe.fecha_nacimiento, pe.sexo,
                                   pe.telefono, pe.direccion, u.correo
                              FROM paciente p
                              JOIN persona pe ON pe.id = p.persona_id
                              LEFT JOIN usuario u ON u.persona_id = p.persona_id';

    /** Lista y busca por CI, nombre, apellido o número de expediente (RF02). */
    public function listarPacientes(string $criterio = '', string $estado = ''): array
    {
        $st = $this->bd->prepare(self::SELECT . "
             WHERE (CAST(:c AS text) = '' OR pe.ci ILIKE :l1 OR p.nro_expediente ILIKE :l2
                    OR (pe.nombres || ' ' || pe.apellidos) ILIKE :l3)
               AND (CAST(:e1 AS text) = '' OR p.estado = :e2)
             ORDER BY pe.apellidos, pe.nombres
             LIMIT 200");
        // PDO con PostgreSQL no permite repetir un mismo :parametro, por eso l1, l2, l3
        $like = '%' . $criterio . '%';
        $st->execute(['c' => $criterio, 'l1' => $like, 'l2' => $like, 'l3' => $like, 'e1' => $estado, 'e2' => $estado]);
        return $st->fetchAll();
    }

    /** Pacientes activos para elegir al agendar (CU14 paso 2). */
    public function buscar(string $criterio): array
    {
        return $this->listarPacientes($criterio, 'ACTIVO');
    }

    public function obtenerPorId(int $persona_id): ?array
    {
        $st = $this->bd->prepare(self::SELECT . ' WHERE p.persona_id = :id');
        $st->execute(['id' => $persona_id]);
        return $st->fetch() ?: null;
    }

    /** Crea el paciente; nro_expediente lo genera la base (EXP-000001). Devuelve el número de expediente. */
    public function insertar(int $persona_id, array $d): string
    {
        $st = $this->bd->prepare('INSERT INTO paciente (persona_id, contacto_emergencia_nombre, contacto_emergencia_parentesco,
                                         contacto_emergencia_telefono, aseguradora_id, nro_poliza)
                                  VALUES (:persona_id, :contacto_emergencia_nombre, :contacto_emergencia_parentesco,
                                         :contacto_emergencia_telefono, :aseguradora_id, :nro_poliza)
                                  RETURNING nro_expediente');
        $st->execute(array_intersect_key($d, array_flip(self::CAMPOS)) + ['persona_id' => $persona_id]);
        return (string) $st->fetchColumn();
    }

    public function actualizar(int $persona_id, array $d): void
    {
        $st = $this->bd->prepare('UPDATE paciente SET contacto_emergencia_nombre = :contacto_emergencia_nombre,
                                         contacto_emergencia_parentesco = :contacto_emergencia_parentesco,
                                         contacto_emergencia_telefono = :contacto_emergencia_telefono,
                                         aseguradora_id = :aseguradora_id, nro_poliza = :nro_poliza
                                   WHERE persona_id = :id');
        $st->execute(array_intersect_key($d, array_flip(self::CAMPOS)) + ['id' => $persona_id]);
    }

    /** Desactivar sin borrar el historial (RN11). */
    public function cambiarEstado(int $persona_id, string $estado): void
    {
        $this->bd->prepare('UPDATE paciente SET estado = :estado WHERE persona_id = :id')
                 ->execute(['estado' => $estado, 'id' => $persona_id]);
    }

    /** Mi perfil: el paciente completa o corrige su contacto de emergencia. */
    public function actualizarContactoEmergencia(int $persona_id, ?string $nombre, ?string $parentesco, ?string $telefono): void
    {
        $this->bd->prepare('UPDATE paciente SET contacto_emergencia_nombre = :n, contacto_emergencia_parentesco = :p,
                                   contacto_emergencia_telefono = :t WHERE persona_id = :id')
                 ->execute(['n' => $nombre, 'p' => $parentesco, 't' => $telefono, 'id' => $persona_id]);
    }

    /**
     * Abre el expediente de una persona que aún no es paciente (reserva en línea): paciente sin datos de
     * contacto (los completa recepción en la primera visita) y ficha clínica vacía. Devuelve el N.º de expediente.
     */
    public function abrirExpediente(int $persona_id): string
    {
        $expediente = $this->insertar($persona_id, ['contacto_emergencia_nombre' => null, 'contacto_emergencia_parentesco' => null,
                                                    'contacto_emergencia_telefono' => null, 'aseguradora_id' => null, 'nro_poliza' => null]);
        (new FichaClinica())->crearFichaVacia($persona_id);
        return $expediente;
    }

    /** CU18 (RF13): pacientes registrados en el periodo, con su cantidad de citas. */
    public function listarRegistrados(string $desde, string $hasta): array
    {
        $st = $this->bd->prepare("SELECT p.nro_expediente AS expediente, pe.nombres || ' ' || pe.apellidos AS paciente, pe.ci,
                                         date_part('year', age(pe.fecha_nacimiento))::int AS edad, pe.sexo,
                                         to_char(p.fecha_registro, 'DD/MM/YYYY') AS registrado,
                                         (SELECT count(*) FROM cita c WHERE c.paciente_id = p.persona_id) AS citas
                                    FROM paciente p JOIN persona pe ON pe.id = p.persona_id
                                   WHERE p.fecha_registro::date BETWEEN CAST(:d AS date) AND CAST(:h AS date)
                                   ORDER BY p.fecha_registro, pe.apellidos LIMIT 2000");
        $st->execute(['d' => $desde, 'h' => $hasta]);
        return $st->fetchAll();
    }
}
