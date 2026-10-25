<?php
namespace App\Controles\Especialidades;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Especialidad;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarEspecialidades — CU08 Gestionar Especialidades.
 * Interfaces: IUEspecialidades, IUFormEspecialidad.  Entidades: Especialidad, Cita, Bitacora.
 * Permisos: módulo ESPECIALIDADES_MEDICOS (C consultar, R registrar, M modificar, A anular).
 */
final class ControlGestionarEspecialidades extends ControlBase
{
    private const MODULO = 'ESPECIALIDADES_MEDICOS';

    public function listarEspecialidades(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $this->vista('Especialidades/IUEspecialidades', [
            'especialidades' => (new Especialidad())->listarEspecialidades(),
        ]);
    }

    public function mostrarFormulario(): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $especialidad = $id ? (new Especialidad())->obtenerPorId($id) : null;
        if ($id && !$especialidad) {
            $this->redirigir('especialidades');
        }
        $this->vista('Especialidades/IUFormEspecialidad', ['especialidad' => $especialidad, 'errores' => []]);
    }

    public function guardarEspecialidad(): void
    {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $entidad = new Especialidad();
        $datos = $this->leerFormulario();
        $errores = $this->validar($datos, $id, $entidad);

        if ($errores) {
            $this->vista('Especialidades/IUFormEspecialidad',
                         ['especialidad' => $datos + ['id' => $id], 'errores' => $errores]);
            return;
        }
        Conexion::transaccion(function () use ($entidad, $datos, &$id) {
            $accion = $id ? 'MODIFICAR_ESPECIALIDAD' : 'CREAR_ESPECIALIDAD';
            if ($id) {
                $entidad->actualizar($id, $datos);
            } else {
                $id = $entidad->insertar($datos);
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), $accion, 'Especialidad: ' . $datos['nombre'],
                                              $this->ip(), 'especialidad', $id);
        });
        Sesion::flash('ok', 'Especialidad guardada correctamente.');
        $this->redirigir('especialidades');
    }

    public function cambiarEstado(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'A');
        $entidad = new Especialidad();
        $especialidad = $entidad->obtenerPorId((int) ($_POST['id'] ?? 0));
        if (!$especialidad) {
            $this->redirigir('especialidades');
        }
        $nuevo = !$especialidad['estado'];
        if (!$nuevo && (new Cita())->contarProgramadasPorEspecialidad((int) $especialidad['id']) > 0) {
            Sesion::flash('error', 'La especialidad tiene citas programadas');
            $this->redirigir('especialidades');
        }
        Conexion::transaccion(function () use ($entidad, $especialidad, $nuevo) {
            $entidad->cambiarEstado((int) $especialidad['id'], $nuevo);
            (new Bitacora())->registrarEvento($this->idUsuario(), $nuevo ? 'ACTIVAR_ESPECIALIDAD' : 'DESACTIVAR_ESPECIALIDAD',
                                              'Especialidad: ' . $especialidad['nombre'], $this->ip(),
                                              'especialidad', (int) $especialidad['id']);
        });
        Sesion::flash('ok', $nuevo ? 'Especialidad reactivada.' : 'Especialidad desactivada.');
        $this->redirigir('especialidades');
    }

    /** Solo los campos del formulario IUFormEspecialidad (mismos nombres que las columnas). */
    private function leerFormulario(): array
    {
        $edadMax = $this->texto('edad_maxima');
        return [
            'nombre'         => $this->texto('nombre'),
            'descripcion'    => $this->texto('descripcion') ?: null,
            'costo_consulta' => $this->texto('costo_consulta'),
            'edad_minima'    => $this->texto('edad_minima') === '' ? '0' : $this->texto('edad_minima'),
            'edad_maxima'    => $edadMax === '' ? null : $edadMax,
            'sexo_permitido' => $this->texto('sexo_permitido') ?: 'A',
        ];
    }

    private function validar(array $d, ?int $id, Especialidad $entidad): array
    {
        $e = [];
        if ($d['nombre'] === '') {
            $e['nombre'] = 'El nombre es obligatorio.';
        } elseif ($entidad->existeNombre($d['nombre'], $id)) {
            $e['nombre'] = 'Ya existe una especialidad con ese nombre';
        }
        if (!is_numeric($d['costo_consulta']) || (float) $d['costo_consulta'] <= 0) {
            $e['costo_consulta'] = 'El costo de la consulta debe ser mayor a cero';
        }
        if (!ctype_digit($d['edad_minima']) || ($d['edad_maxima'] !== null && !ctype_digit($d['edad_maxima']))) {
            $e['edad_minima'] = 'Las edades deben ser números enteros.';
        } elseif ($d['edad_maxima'] !== null && (int) $d['edad_minima'] > (int) $d['edad_maxima']) {
            $e['edad_minima'] = 'La edad mínima no puede ser mayor que la máxima';
        }
        if (!in_array($d['sexo_permitido'], ['A', 'F', 'M'], true)) {
            $e['sexo_permitido'] = 'Seleccione Ambos, Femenino o Masculino.';
        }
        return $e;
    }
}
