<?php
namespace App\Controles\Especialidades;

use App\Entidades\Bitacora;
use App\Entidades\Especialidad;
use App\Entidades\Medico;
use App\Entidades\Persona;
use App\Entidades\Rol;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarMedicos — CU09 Gestionar Médicos (RF03).   Responsable: Erika
 * Interfaces: IUMedicos, IUFormMedico.  Entidades: Persona, Medico, Especialidad, Usuario, Bitacora.
 * Permisos: módulo ESPECIALIDADES_MEDICOS. Al registrar se crea la cuenta con rol MEDICO
 * (contraseña temporal = número de documento, debe cambiarla en su primer ingreso).
 */
final class ControlGestionarMedicos extends ControlBase
{
    private const MODULO = 'ESPECIALIDADES_MEDICOS';

    public function listarMedicos(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $criterio = trim($_GET['criterio'] ?? '');
        $especialidadId = (int) ($_GET['especialidad_id'] ?? 0) ?: null;
        $this->vista('Especialidades/IUMedicos', [
            'medicos'         => (new Medico())->listarMedicos($criterio, $especialidadId),
            'especialidades'  => (new Especialidad())->listarActivas(),
            'criterio'        => $criterio,
            'especialidad_id' => $especialidadId,
        ]);
    }

    public function mostrarFormulario(): void
    {
        $id = (int) ($_GET['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $medico = $id ? (new Medico())->obtenerPorId($id) : null;
        if ($id && !$medico) {
            $this->redirigir('medicos');
        }
        $this->vista('Especialidades/IUFormMedico', ['medico' => $medico, 'errores' => [],
                                                     'especialidades' => (new Especialidad())->listarActivas()]);
    }

    public function guardarMedico(): void
    {
        $id = (int) ($_POST['persona_id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $medicos = new Medico();
        $actual = $id ? $medicos->obtenerPorId($id) : null;
        if ($id && !$actual) {
            $this->redirigir('medicos');
        }
        $d = $this->leerFormulario();
        $errores = $this->validar($d, $actual, $medicos);
        if ($errores) {
            $this->vista('Especialidades/IUFormMedico', ['medico' => $d + ['persona_id' => $id], 'errores' => $errores,
                                                         'especialidades' => (new Especialidad())->listarActivas()]);
            return;
        }
        Conexion::transaccion(function () use ($d, $id, $actual, $medicos) {
            $personas = new Persona();
            if ($id) {
                $personas->actualizar($id, $d);
                $medicos->actualizar($id, $d['matricula']);
                $accion = 'MODIFICAR_MEDICO';
            } else {
                $id = $personas->insertar($d);
                $medicos->insertar($id, $d['matricula']);
                (new Usuario())->insertar($id, (new Rol())->obtenerIdPorNombre('MEDICO'), $d['correo'], $d['ci']);
                $accion = 'CREAR_MEDICO';
            }
            $medicos->asignarEspecialidades($id, $d['especialidades']);
            (new Bitacora())->registrarEvento($this->idUsuario(), $accion,
                                              'Médico ' . $d['matricula'] . ': ' . $d['nombres'] . ' ' . $d['apellidos'],
                                              $this->ip(), 'medico', $id);
        });
        Sesion::flash('ok', $actual ? 'Médico modificado correctamente.' : 'Médico registrado correctamente.');
        $this->redirigir('medicos');
    }

    private function leerFormulario(): array
    {
        $d = [];
        foreach (Persona::CAMPOS as $c) {
            $d[$c] = $this->texto($c);
        }
        $d['telefono'] = $d['telefono'] ?: null;
        $d['direccion'] = $d['direccion'] ?: null;
        $d['matricula'] = $this->texto('matricula');
        $d['correo'] = strtolower($this->texto('correo'));
        $d['especialidades'] = array_values(array_unique(array_map('intval', (array) ($_POST['especialidades'] ?? []))));
        return $d;
    }

    private function validar(array $d, ?array $actual, Medico $medicos): array
    {
        $e = [];
        foreach (['ci' => 'el documento', 'nombres' => 'los nombres', 'apellidos' => 'los apellidos',
                  'fecha_nacimiento' => 'la fecha de nacimiento', 'matricula' => 'la matrícula'] as $c => $t) {
            if ($d[$c] === '') {
                $e[$c] = 'Complete ' . $t . '.';
            }
        }
        if (!in_array($d['sexo'], ['F', 'M'], true)) {
            $e['sexo'] = 'Seleccione el sexo.';
        }
        $excluir = $actual ? (int) $actual['persona_id'] : null;
        if (($d['ci'] !== '' && (new Persona())->existeCi($d['ci'], $excluir))
            || ($d['matricula'] !== '' && $medicos->existeMatricula($d['matricula'], $excluir))) {
            $e['matricula'] = 'Ya existe un médico con esa matrícula o documento';
        }
        if (!$d['especialidades']) {
            $e['especialidades'] = 'Seleccione al menos una especialidad';
        }
        if (!$actual) {   // el correo solo se registra al crear (luego se cambia en Usuarios, CU02)
            if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
                $e['correo'] = 'Ingrese un correo válido.';
            } elseif ((new Usuario())->existeCorreo($d['correo'])) {
                $e['correo'] = 'El correo ya está registrado en otra cuenta.';
            }
        }
        return $e;
    }
}
