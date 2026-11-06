<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/** «entity» Bitacora — tabla bitacora (solo inserción, RN14). */
final class Bitacora extends EntidadBase
{
    public function registrarEvento(?int $usuario_id, string $accion, string $descripcion, ?string $ip,
                                    ?string $tabla = null, ?int $registro_id = null): void
    {
        $this->bd->prepare('SELECT fn_registrar_bitacora(:usuario, :accion, :descripcion, CAST(:ip AS inet), :tabla, :registro)')
                 ->execute(['usuario' => $usuario_id, 'accion' => $accion, 'descripcion' => $descripcion,
                            'ip' => $ip, 'tabla' => $tabla, 'registro' => $registro_id]);
    }

    /**
     * Consulta de la bitácora (solo lectura): filtros por fechas, usuario (correo o nombre) y acción.
     * Devuelve ['filas' => [...], 'total' => n] con paginación de $porPagina registros.
     */
    public function listar(array $f, int $pagina = 1, int $porPagina = 50): array
    {
        $where = "WHERE (CAST(:desde AS date) IS NULL OR b.fecha_hora >= CAST(:desde2 AS date))
                    AND (CAST(:hasta AS date) IS NULL OR b.fecha_hora < CAST(:hasta2 AS date) + 1)
                    AND (CAST(:usuario AS text) = '' OR u.correo ILIKE :u1 OR (p.nombres || ' ' || p.apellidos) ILIKE :u2)
                    AND (CAST(:accion AS text) = '' OR b.accion = :accion2)";
        $desde = $f['desde'] ?: null;
        $hasta = $f['hasta'] ?: null;
        $like = '%' . $f['usuario'] . '%';
        $params = ['desde' => $desde, 'desde2' => $desde, 'hasta' => $hasta, 'hasta2' => $hasta,
                   'usuario' => $f['usuario'], 'u1' => $like, 'u2' => $like, 'accion' => $f['accion'], 'accion2' => $f['accion']];
        $desdeJoin = 'FROM bitacora b
                      LEFT JOIN usuario u ON u.id = b.usuario_id
                      LEFT JOIN persona p ON p.id = u.persona_id ';
        $st = $this->bd->prepare('SELECT count(*) ' . $desdeJoin . $where);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $this->bd->prepare("SELECT b.id, b.fecha_hora, b.accion, b.descripcion, b.tabla, b.registro_id, host(b.ip) AS ip,
                                         u.correo, p.nombres || ' ' || p.apellidos AS nombre "
                                 . $desdeJoin . $where . ' ORDER BY b.fecha_hora DESC, b.id DESC LIMIT ' . $porPagina
                                 . ' OFFSET ' . (max(1, $pagina) - 1) * $porPagina);
        $st->execute($params);
        return ['filas' => $st->fetchAll(), 'total' => $total];
    }

    /** Acciones distintas registradas, para el filtro. */
    public function listarAcciones(): array
    {
        return $this->bd->query('SELECT DISTINCT accion FROM bitacora ORDER BY accion')->fetchAll(\PDO::FETCH_COLUMN);
    }
}
