<?php
namespace App\Controles\Pacientes;

use App\Entidades\Bitacora;
use App\Entidades\FichaClinica;
use App\Entidades\Paciente;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarFichaClinica — CU21 Gestionar Ficha Clínica Básica.   Ciclo 2 · P8 · Responsable: Jackeline
 * Interfaces: IUFichaClinica.  Entidades: Paciente, FichaClinica, Bitacora.
 * Permisos: PACIENTES/C consultar, PACIENTES/M modificar (matriz RBAC de 03_datos_base.sql, editable en CU03).
 * La ficha nace vacía con CU05 ("No especificado"); si un paciente antiguo no la tiene, se crea al abrirla.
 * fecha_actualizacion la pone el trigger tg_ficha_fecha; quién la cambió queda en la bitácora.
 */
final class ControlGestionarFichaClinica extends ControlBase
{
    private const MODULO = 'PACIENTES';

    public function mostrarFicha(): void
    {
        $pacienteId = $this->exigirAccesoPaciente(self::MODULO, (int) ($_GET['paciente_id'] ?? 0) ?: null);
        if (!$pacienteId) {                                         // paso 1: elegir paciente
            $this->mostrarBusqueda();
            return;
        }
        $paciente = $this->obtenerPaciente($pacienteId);
        $fichas = new FichaClinica();
        $fichas->crearFichaVacia($pacienteId);
        $this->vista('Pacientes/IUFichaClinica', [
            'paciente' => $paciente, 'ficha' => $fichas->obtenerPorPaciente($pacienteId),
            'errores' => [], 'puedeModificar' => Sesion::puede(self::MODULO, 'M'),
        ]);
    }

    public function guardarFicha(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
        $paciente = $this->obtenerPaciente($pacienteId);
        $d = $this->leerFormulario();
        $errores = $this->validar($d);
        if ($errores) {
            $this->vista('Pacientes/IUFichaClinica', ['paciente' => $paciente, 'ficha' => $d,
                                                      'errores' => $errores, 'puedeModificar' => true]);
            return;
        }
        Conexion::transaccion(function () use ($pacienteId, $d, $paciente) {
            $fichas = new FichaClinica();
            $fichas->crearFichaVacia($pacienteId);
            $fichas->actualizar($pacienteId, $d);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'MODIFICAR_FICHA_CLINICA',
                                              'Ficha clínica del paciente ' . $paciente['nro_expediente'],
                                              $this->ip(), 'ficha_clinica', $pacienteId);
        });
        Sesion::flash('ok', 'Ficha clínica actualizada.');
        $this->redirigir('ficha&paciente_id=' . $pacienteId);
    }

    private function mostrarBusqueda(): void
    {
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        $this->vista('Pacientes/IUBuscarPaciente', ['titulo' => 'Ficha clínica', 'rutaBusqueda' => 'ficha', 'criterio' => $criterio,
                     'pacientes' => $criterio !== '' ? (new Paciente())->listarPacientes($criterio) : []]);
    }

    private function obtenerPaciente(int $id): array
    {
        $paciente = (new Paciente())->obtenerPorId($id);
        if (!$paciente) {
            Sesion::flash('error', 'El paciente no existe.');
            $this->redirigir('ficha');
        }
        return $paciente;
    }

    /** Campo vacío = "No especificado" (mismo valor por defecto de la tabla). */
    private function leerFormulario(): array
    {
        $d = [];
        foreach (FichaClinica::CAMPOS as $c) {
            $d[$c] = $this->texto($c) ?: 'No especificado';
        }
        return $d;
    }

    private function validar(array $d): array
    {
        $e = [];
        if (!in_array($d['tipo_sangre'], FichaClinica::TIPOS_SANGRE, true)) {
            $e['tipo_sangre'] = 'Seleccione un tipo de sangre de la lista.';
        }
        foreach (FichaClinica::CAMPOS as $c) {
            if ($c !== 'tipo_sangre' && mb_strlen($d[$c]) > 1000) {
                $e[$c] = 'Máximo 1000 caracteres.';
            }
        }
        return $e;
    }
}
