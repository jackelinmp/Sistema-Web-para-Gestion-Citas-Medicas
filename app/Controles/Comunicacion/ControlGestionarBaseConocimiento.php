<?php
namespace App\Controles\Comunicacion;

use App\Entidades\Bitacora;
use App\Entidades\PreguntaFrecuente;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarBaseConocimiento — CU26 Base de conocimiento.
 * Interfaces: IUConocimiento, IUFormConocimiento.  Entidades: PreguntaFrecuente, Bitacora.
 * Alimenta al asistente virtual (CU25) y registra las preguntas sin respuesta.
 */
final class ControlGestionarBaseConocimiento extends ControlBase
{
    private const MODULO = 'COMUNICACION';

    public function listarPreguntas(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $categoria = (string) ($_GET['categoria'] ?? '');
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        $this->vista('Comunicacion/IUConocimiento', [
            'preguntas'   => (new PreguntaFrecuente())->listar($categoria, $criterio),
            'categorias'  => PreguntaFrecuente::CATEGORIAS,
            'categoria'   => $categoria,
            'criterio'    => $criterio,
            'sinRespuesta' => (new \App\Entidades\Conversacion())->listarSinRespuesta(),
        ]);
    }

    public function mostrarFormulario(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $pregunta = $id ? (new PreguntaFrecuente())->obtenerPorId($id) : null;
        if ($id && !$pregunta) {
            $this->redirigir('conocimiento');
        }
        $this->vista('Comunicacion/IUFormConocimiento', [
            'pregunta'    => $pregunta,
            'categorias'  => PreguntaFrecuente::CATEGORIAS,
            'errores'     => [],
        ]);
    }

    public function guardarPregunta(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $entidad = new PreguntaFrecuente();
        $datos = $this->leerFormulario();
        $errores = $this->validar($datos, $id, $entidad);

        if ($errores) {
            $this->vista('Comunicacion/IUFormConocimiento', [
                'pregunta'   => $datos + ['id' => $id],
                'categorias' => PreguntaFrecuente::CATEGORIAS,
                'errores'    => $errores,
            ]);
            return;
        }
        Conexion::transaccion(function () use ($entidad, $datos, &$id) {
            $accion = $id ? 'MODIFICAR_PREGUNTA_FRECUENTE' : 'CREAR_PREGUNTA_FRECUENTE';
            if ($id) {
                $entidad->actualizar($id, $datos);
            } else {
                $id = $entidad->registrar($datos);
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), $accion, 'Pregunta: ' . $datos['pregunta'], $this->ip(), 'pregunta_frecuente', $id);
        });
        Sesion::flash('ok', 'Pregunta guardada correctamente.');
        $this->redirigir('conocimiento');
    }

    public function cambiarEstado(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $id = (int) ($_POST['id'] ?? 0);
        $pregunta = (new PreguntaFrecuente())->obtenerPorId($id);
        if (!$pregunta) {
            $this->redirigir('conocimiento');
        }
        $activa = !in_array($pregunta['estado'], [true, 't', 'true', 1, '1'], true);
        (new PreguntaFrecuente())->cambiarEstado($id, $activa);
        (new Bitacora())->registrarEvento($this->idUsuario(), $activa ? 'ACTIVAR_PREGUNTA' : 'DESACTIVAR_PREGUNTA',
                                          'Pregunta: ' . $pregunta['pregunta'], $this->ip(), 'pregunta_frecuente', $id);
        Sesion::flash('ok', $activa ? 'Pregunta activada.' : 'Pregunta desactivada.');
        $this->redirigir('conocimiento');
    }

    private function leerFormulario(): array
    {
        return [
            'categoria'      => $this->texto('categoria'),
            'pregunta'       => $this->texto('pregunta'),
            'respuesta'      => $this->texto('respuesta'),
            'palabras_clave' => $this->texto('palabras_clave'),
        ];
    }

    private function validar(array $d, ?int $id, PreguntaFrecuente $entidad): array
    {
        $e = [];
        if ($d['pregunta'] === '') {
            $e['pregunta'] = 'La pregunta es obligatoria.';
        } elseif ($entidad->existePregunta($d['pregunta'], $id)) {
            $e['pregunta'] = 'Ya existe una pregunta con ese texto.';
        }
        if ($d['respuesta'] === '') {
            $e['respuesta'] = 'La respuesta es obligatoria.';
        }
        if (!array_key_exists($d['categoria'], PreguntaFrecuente::CATEGORIAS)) {
            $e['categoria'] = 'Seleccione una categoría válida.';
        }
        return $e;
    }
}