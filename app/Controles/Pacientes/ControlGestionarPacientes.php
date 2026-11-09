<?php
namespace App\Controles\Pacientes;

use App\Entidades\Aseguradora;
use App\Entidades\Bitacora;
use App\Entidades\FichaClinica;
use App\Entidades\Paciente;
use App\Entidades\Persona;
use App\Entidades\Rol;
use App\Entidades\Usuario;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarPacientes — CU05 Gestionar Pacientes (RF02).   Responsable: Erika
 * Interfaces: IUPacientes, IUFormPaciente.
 * Entidades: Persona, Paciente, Usuario, Aseguradora, FichaClinica, Bitacora.
 * Permisos: módulo PACIENTES. La cuenta de acceso (rol PACIENTE) se crea solo si se registra un correo.
 */
final class ControlGestionarPacientes extends ControlBase
{
    private const MODULO = 'PACIENTES';

    public function listarPacientes(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $criterio = trim($_GET['criterio'] ?? '');
        $estado = in_array($_GET['estado'] ?? '', ['ACTIVO', 'INACTIVO'], true) ? $_GET['estado'] : '';
        $this->vista('Pacientes/IUPacientes', [
            'pacientes' => (new Paciente())->listarPacientes($criterio, $estado),
            'criterio'  => $criterio,
            'estado'    => $estado,
        ]);
    }

    public function mostrarFormulario(): void
    {
        $id = (int) ($_GET['id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $paciente = $id ? (new Paciente())->obtenerPorId($id) : null;
        if ($id && !$paciente) {
            $this->redirigir('pacientes');
        }
        $this->vista('Pacientes/IUFormPaciente', ['paciente' => $paciente, 'errores' => [],
                                                  'aseguradoras' => (new Aseguradora())->listarAseguradoras()]);
    }

    public function guardarPaciente(): void
    {
        $id = (int) ($_POST['persona_id'] ?? 0) ?: null;
        Sesion::exigirPermiso(self::MODULO, $id ? 'M' : 'R');
        $pacientes = new Paciente();
        $actual = $id ? $pacientes->obtenerPorId($id) : null;
        if ($id && !$actual) {
            $this->redirigir('pacientes');
        }
        $d = $this->leerFormulario();
        $errores = $this->validar($d, $actual);
        if ($errores) {
            $this->vista('Pacientes/IUFormPaciente', ['paciente' => $d + ['persona_id' => $id], 'errores' => $errores,
                                                      'aseguradoras' => (new Aseguradora())->listarAseguradoras()]);
            return;
        }
        $expediente = Conexion::transaccion(function () use ($d, $id, $actual, $pacientes) {
            $personas = new Persona();
            if ($id) {
                $personas->actualizar($id, $d);
                $pacientes->actualizar($id, $d);
                $accion = 'MODIFICAR_PACIENTE';
                $expediente = $actual['nro_expediente'];
            } else {
                $id = $personas->insertar($d);
                $expediente = $pacientes->insertar($id, $d);
                (new FichaClinica())->crearFichaVacia($id);
                $accion = 'CREAR_PACIENTE';
            }
            // Cuenta de acceso con rol PACIENTE solo si hay correo y aún no tiene cuenta
            if ($d['correo'] !== '' && empty($actual['correo'])) {
                (new Usuario())->insertar($id, (new Rol())->obtenerIdPorNombre('PACIENTE'), $d['correo'], $d['ci']);
            }
            (new Bitacora())->registrarEvento($this->idUsuario(), $accion,
                                              'Paciente ' . $expediente . ': ' . $d['nombres'] . ' ' . $d['apellidos'],
                                              $this->ip(), 'paciente', $id);
            return $expediente;
        });
        Sesion::flash('ok', 'Paciente guardado correctamente. Expediente: ' . $expediente);
        $this->redirigir('pacientes');
    }

    /** Desactivar / reactivar sin eliminar el historial (RN11). */
    public function cambiarEstado(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $id = (int) ($_POST['persona_id'] ?? 0);
        $pacientes = new Paciente();
        $paciente = $pacientes->obtenerPorId($id);
        if (!$paciente) {
            $this->redirigir('pacientes');
        }
        $nuevo = $paciente['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        Conexion::transaccion(function () use ($pacientes, $id, $nuevo, $paciente) {
            $pacientes->cambiarEstado($id, $nuevo);
            (new Bitacora())->registrarEvento($this->idUsuario(), $nuevo === 'INACTIVO' ? 'DESACTIVAR_PACIENTE' : 'ACTIVAR_PACIENTE',
                                              'Paciente ' . $paciente['nro_expediente'], $this->ip(), 'paciente', $id);
        });
        Sesion::flash('ok', $nuevo === 'INACTIVO' ? 'Paciente desactivado.' : 'Paciente reactivado.');
        $this->redirigir('pacientes');
    }

    private function leerFormulario(): array
    {
        $d = [];
        foreach (array_merge(Persona::CAMPOS, Paciente::CAMPOS) as $c) {
            $d[$c] = $this->texto($c);
        }
        foreach (['telefono', 'direccion', 'nro_poliza'] as $opcional) {
            $d[$opcional] = $d[$opcional] ?: null;
        }
        $d['aseguradora_id'] = $d['aseguradora_id'] === '' ? null : (int) $d['aseguradora_id'];
        $d['correo'] = strtolower($this->texto('correo'));
        return $d;
    }

    private function validar(array $d, ?array $actual): array
    {
        $e = [];
        $obligatorios = ['ci' => 'el documento', 'nombres' => 'los nombres', 'apellidos' => 'los apellidos',
                         'fecha_nacimiento' => 'la fecha de nacimiento',
                         'contacto_emergencia_nombre' => 'el contacto de emergencia',
                         'contacto_emergencia_parentesco' => 'el parentesco', 'contacto_emergencia_telefono' => 'el teléfono de emergencia'];
        foreach ($obligatorios as $c => $t) {
            if ($d[$c] === '') {
                $e[$c] = 'Complete ' . $t . '.';
            }
        }
        if (!in_array($d['sexo'], ['F', 'M'], true)) {
            $e['sexo'] = 'Seleccione el sexo.';
        }
        if ($d['fecha_nacimiento'] !== '' && ($d['fecha_nacimiento'] > date('Y-m-d') || $d['fecha_nacimiento'] <= '1900-01-01')) {
            $e['fecha_nacimiento'] = 'La fecha de nacimiento no es válida.';
        }
        if ($d['ci'] !== '' && (new Persona())->existeCi($d['ci'], $actual ? (int) $actual['persona_id'] : null)) {
            $e['ci'] = 'Ya existe un paciente con ese documento';
        }
        if ($d['correo'] !== '') {
            if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
                $e['correo'] = 'Ingrese un correo válido.';
            } elseif (empty($actual['correo']) && (new Usuario())->existeCorreo($d['correo'])) {
                $e['correo'] = 'El correo electrónico ya está asociado a otra cuenta';
            }
        }
        if (($d['aseguradora_id'] === null) !== ($d['nro_poliza'] === null)) {
            $e['nro_poliza'] = 'Si el paciente tiene seguro, indique la aseguradora y el número de póliza.';
        }
        return $e;
    }
}
