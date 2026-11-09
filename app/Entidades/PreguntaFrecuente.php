<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» PreguntaFrecuente — tabla pregunta_frecuente (CU26). Es la base de conocimiento del asistente virtual (CU25). */
final class PreguntaFrecuente extends EntidadBase
{
    public const CATEGORIAS = ['CITAS' => 'Citas', 'PAGOS' => 'Pagos', 'EXAMENES' => 'Exámenes',
                               'CUENTA' => 'Cuenta', 'GENERAL' => 'General'];

    /** Palabras que no ayudan a reconocer la pregunta. */
    private const PALABRAS_VACIAS = ['para', 'como', 'cual', 'cuales', 'donde', 'cuando', 'quiero', 'queria', 'puedo', 'tengo',
                                     'esta', 'este', 'esto', 'estoy', 'sobre', 'desde', 'hasta', 'porque', 'unos', 'unas',
                                     'necesito', 'saber', 'hacer', 'tiene', 'tienen', 'favor', 'ustedes', 'usted', 'mucho', 'algo'];

    public function listar(string $categoria = '', string $criterio = ''): array
    {
        $st = $this->bd->prepare("SELECT id, categoria, pregunta, respuesta, palabras_clave, estado, fecha_actualizacion
                                    FROM pregunta_frecuente
                                   WHERE (CAST(:c AS text) = '' OR categoria = :c2)
                                     AND (CAST(:t AS text) = '' OR pregunta ILIKE :l1 OR palabras_clave ILIKE :l2)
                                   ORDER BY estado DESC, categoria, pregunta");
        $like = '%' . $criterio . '%';
        $st->execute(['c' => $categoria, 'c2' => $categoria, 't' => $criterio, 'l1' => $like, 'l2' => $like]);
        return $st->fetchAll();
    }

    public function obtenerPorId(int $id): ?array
    {
        $st = $this->bd->prepare('SELECT id, categoria, pregunta, respuesta, palabras_clave, estado FROM pregunta_frecuente WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function registrar(array $d): int
    {
        $st = $this->bd->prepare('INSERT INTO pregunta_frecuente (categoria, pregunta, respuesta, palabras_clave)
                                  VALUES (:categoria, :pregunta, :respuesta, :palabras_clave) RETURNING id');
        $st->execute($d);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, array $d): void
    {
        $this->bd->prepare('UPDATE pregunta_frecuente SET categoria = :categoria, pregunta = :pregunta,
                                   respuesta = :respuesta, palabras_clave = :palabras_clave WHERE id = :id')
                 ->execute($d + ['id' => $id]);
    }

    public function cambiarEstado(int $id, bool $activa): void
    {
        $this->bd->prepare('UPDATE pregunta_frecuente SET estado = :e WHERE id = :id')
                 ->execute(['e' => $activa ? 'true' : 'false', 'id' => $id]);
    }

    public function existePregunta(string $pregunta, ?int $excluirId = null): bool
    {
        $st = $this->bd->prepare('SELECT 1 FROM pregunta_frecuente WHERE lower(pregunta) = lower(:p) AND id IS DISTINCT FROM :id');
        $st->execute(['p' => $pregunta, 'id' => $excluirId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * CU25: la pregunta activa que más palabras comparte con el texto del paciente.
     * Cuenta las palabras de 4 letras o más que aparecen en la pregunta o en sus palabras clave
     * (sin tildes ni mayúsculas), sin contar palabras vacías («para», «quiero»...).
     * Palabra completa = 2 puntos, misma raíz de 5 letras = 1 punto. Devuelve null si ninguna alcanza el mínimo.
     */
    public function buscarRespuesta(string $texto, int $minimo = 2): ?array
    {
        $palabras = array_unique(array_filter(explode(' ', self::normalizar($texto)),
                                              fn($p) => mb_strlen($p) >= 4 && !in_array($p, self::PALABRAS_VACIAS, true)));
        if (!$palabras) {
            return null;
        }
        $mejor = null;
        $mejorPuntaje = 0;
        foreach ($this->bd->query('SELECT id, pregunta, respuesta, palabras_clave FROM pregunta_frecuente WHERE estado ORDER BY id') as $f) {
            $base = ' ' . self::normalizar($f['pregunta'] . ' ' . $f['palabras_clave']) . ' ';
            $puntaje = 0;
            foreach ($palabras as $p) {
                // coincide la palabra completa o su raíz (cancelar / cancelo / cancelación)
                $puntaje += str_contains($base, ' ' . $p . ' ') ? 2 : (str_contains($base, ' ' . mb_substr($p, 0, 5)) ? 1 : 0);
            }
            if ($puntaje > $mejorPuntaje) {
                [$mejor, $mejorPuntaje] = [$f, $puntaje];
            }
        }
        return $mejorPuntaje >= $minimo ? $mejor : null;
    }

    /** Minúsculas, sin tildes ni signos: «¿Cómo AGENDO?» -> «como agendo». */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\/ ]/', ' ', $texto)));
    }

    /** CU25: preguntas activas para los botones de sugerencia del chat (las que sí tienen respuesta). */
    public function listarSugerencias(int $cantidad): array
    {
        $st = $this->bd->prepare('SELECT pregunta FROM pregunta_frecuente WHERE estado ORDER BY id LIMIT ' . (int) $cantidad);
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_COLUMN);
    }
}
