<?php
namespace App\Entidades;

use App\Nucleo\EntidadBase;

/**
 * «entity» ResultadoLaboratorio — tabla resultado_laboratorio (CU23). Un resultado por solicitud.
 * Al insertarlo, el trigger tg_resultado_listo pasa la solicitud a RESULTADO_LISTO y avisa al paciente.
 * El archivo (PDF/JPG/PNG) se guarda en la columna archivo (bytea): en Vercel no hay disco persistente.
 */
final class ResultadoLaboratorio extends EntidadBase
{
    public const TIPOS_ARCHIVO = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG'];

    public function insertar(array $d): int
    {
        $st = $this->bd->prepare('INSERT INTO resultado_laboratorio (solicitud_examen_id, resultado, archivo_nombre, tipo_archivo, tamano_bytes, archivo)
                                  VALUES (:solicitud, :resultado, :nombre, :tipo, :tamano, :archivo) RETURNING id');
        $st->bindValue(':solicitud', $d['solicitud_examen_id'], \PDO::PARAM_INT);
        $st->bindValue(':resultado', $d['resultado']);
        $st->bindValue(':nombre', $d['archivo_nombre']);
        $st->bindValue(':tipo', $d['tipo_archivo']);
        $st->bindValue(':tamano', $d['tamano_bytes'], $d['tamano_bytes'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $st->bindValue(':archivo', $d['archivo'], $d['archivo'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_LOB);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /** Archivo del resultado de una solicitud (se lee aparte para no cargarlo en las listas). */
    public function obtenerArchivo(int $solicitud_id): ?array
    {
        $st = $this->bd->prepare('SELECT archivo_nombre, tipo_archivo, archivo FROM resultado_laboratorio
                                   WHERE solicitud_examen_id = :s AND archivo IS NOT NULL');
        $st->execute(['s' => $solicitud_id]);
        $fila = $st->fetch();
        if (!$fila) {
            return null;
        }
        if (is_resource($fila['archivo'])) {
            $fila['archivo'] = stream_get_contents($fila['archivo']);
        }
        return $fila;
    }
}
