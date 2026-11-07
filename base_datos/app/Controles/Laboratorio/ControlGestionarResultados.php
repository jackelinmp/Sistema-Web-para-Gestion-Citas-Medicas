<?php
namespace App\Controles\Laboratorio;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Paciente;
use App\Entidades\ResultadoLaboratorio;
use App\Entidades\SolicitudExamen;
use App\Entidades\TipoExamen;
use App\Nucleo\Conexion;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGestionarResultados — CU23 Gestionar Resultados de Laboratorio.   Ciclo 2 · P8 · Responsable: Jackeline
 * Interfaces: IUResultados, IUSolicitudExamen, IUFormResultado.
 * Entidades: Paciente, Cita, TipoExamen, SolicitudExamen, ResultadoLaboratorio, Bitacora.
 * Permisos: EXAMENES/C consultar (el PACIENTE solo lo suyo y lo de su grupo familiar), EXAMENES/R solicitar,
 *           EXAMENES/M en proceso, cargar resultado, entregar y cancelar.
 * Flujo: SOLICITADO -> EN_PROCESO -> RESULTADO_LISTO (lo pone tg_resultado_listo al cargar el resultado) -> ENTREGADO
 *        SOLICITADO / EN_PROCESO -> CANCELADO
 * Reglas: la base solo acepta solicitudes en citas CONFIRMADAS o ATENDIDAS y de exámenes activos (tg_solicitud_examen_valida).
 *         El resultado lleva texto o archivo (PDF, JPG o PNG, máx. 2 MB), al menos uno. Un resultado por solicitud.
 */
final class ControlGestionarResultados extends ControlBase
{
    private const MODULO = 'EXAMENES';
    private const MAX_BYTES = 2 * 1024 * 1024;

    public function listarResultados(): void
    {
        $pacienteId = $this->exigirAccesoPaciente(self::MODULO, (int) ($_GET['paciente_id'] ?? 0) ?: null);
        $criterio = trim((string) ($_GET['criterio'] ?? ''));
        if (!$pacienteId) {                                         // paso 1: elegir paciente (personal)
            $this->vista('Laboratorio/IUResultados', [
                'paciente' => null, 'criterio' => $criterio,
                'pacientes' => $criterio !== '' ? (new Paciente())->listarPacientes($criterio) : [],
            ]);
            return;
        }
        $this->vista('Laboratorio/IUResultados', [
            'paciente' => $this->obtenerPaciente($pacienteId),
            'examenes' => (new SolicitudExamen())->listarPorPaciente($pacienteId, !$this->esPaciente()),
            'esPaciente' => $this->esPaciente(),
        ]);
    }

    public function mostrarSolicitud(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'R');
        $paciente = $this->obtenerPaciente((int) ($_GET['paciente_id'] ?? 0));
        $this->mostrarFormularioSolicitud($paciente, [], []);
    }

    public function solicitarExamen(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'R');
        $paciente = $this->obtenerPaciente((int) ($_POST['paciente_id'] ?? 0));
        $citas = (new Cita())->listarParaExamen((int) $paciente['persona_id']);
        $tipos = (new TipoExamen())->listarActivos();
        $d = ['cita_id' => (int) ($_POST['cita_id'] ?? 0), 'tipo_examen_id' => (int) ($_POST['tipo_examen_id'] ?? 0)];
        $e = [];
        if (!in_array($d['cita_id'], array_map('intval', array_column($citas, 'id')), true)) {
            $e['cita_id'] = 'Seleccione una cita confirmada o atendida de este paciente.';
        }
        if (!in_array($d['tipo_examen_id'], array_map('intval', array_column($tipos, 'id')), true)) {
            $e['tipo_examen_id'] = 'Seleccione un examen del catálogo.';
        }
        if ($e) {
            $this->mostrarFormularioSolicitud($paciente, $d, $e);
            return;
        }
        try {
            Conexion::transaccion(fn () => (new SolicitudExamen())->solicitar($d['cita_id'], $d['tipo_examen_id'], (int) $this->idUsuario()));
        } catch (\PDOException $ex) {                               // regla de tg_solicitud_examen_valida (RAISE EXCEPTION)
            if (($ex->errorInfo[0] ?? '') !== 'P0001') {
                throw $ex;
            }
            $this->mostrarFormularioSolicitud($paciente, $d, ['general' => trim(preg_replace('/^.*ERROR:\s*|\n.*$/s', '', $ex->getMessage()))]);
            return;
        }
        Sesion::flash('ok', 'Examen solicitado. Cuando llegue el resultado, cárguelo desde esta lista.');
        $this->redirigir('laboratorio&paciente_id=' . $paciente['persona_id']);
    }

    public function marcarEnProceso(): void
    {
        $this->cambiarEstado('EN_PROCESO', ['SOLICITADO'], 'Examen marcado en proceso.');
    }

    public function mostrarFormulario(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $examen = $this->obtenerExamen((int) ($_GET['id'] ?? 0), ['SOLICITADO', 'EN_PROCESO']);
        $this->vista('Laboratorio/IUFormResultado', ['examen' => $examen, 'resultado' => [], 'errores' => []]);
    }

    public function registrarResultado(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $examen = $this->obtenerExamen((int) ($_POST['id'] ?? 0), ['SOLICITADO', 'EN_PROCESO']);
        [$d, $errores] = $this->leerResultado((int) $examen['id']);
        if ($errores) {
            $this->vista('Laboratorio/IUFormResultado', ['examen' => $examen, 'resultado' => $d, 'errores' => $errores]);
            return;
        }
        Conexion::transaccion(function () use ($d, $examen) {
            $id = (new ResultadoLaboratorio())->insertar($d);       // tg_resultado_listo: RESULTADO_LISTO + aviso al paciente
            (new Bitacora())->registrarEvento($this->idUsuario(), 'REGISTRAR_RESULTADO_LAB',
                                              $examen['examen'] . ' del paciente ' . $examen['nro_expediente'],
                                              $this->ip(), 'resultado_laboratorio', $id);
        });
        Sesion::flash('ok', 'Resultado registrado. Ya está disponible para el paciente.');
        $this->redirigir('laboratorio&paciente_id=' . $examen['paciente_id']);
    }

    public function marcarEntregado(): void
    {
        $this->cambiarEstado('ENTREGADO', ['RESULTADO_LISTO'], 'Resultado marcado como entregado.');
    }

    public function cancelarSolicitud(): void
    {
        $this->cambiarEstado('CANCELADO', ['SOLICITADO', 'EN_PROCESO'], 'Solicitud de examen cancelada.');
    }

    public function descargarArchivo(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $examen = (new SolicitudExamen())->obtenerPorId((int) ($_GET['id'] ?? 0));
        $archivo = $examen ? (new ResultadoLaboratorio())->obtenerArchivo((int) $examen['id']) : null;
        if (!$archivo) {
            Sesion::flash('error', 'El resultado no tiene archivo adjunto.');
            $this->redirigir('laboratorio');
        }
        $this->exigirAccesoPaciente(self::MODULO, (int) $examen['paciente_id']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $tipo = array_search($archivo['tipo_archivo'], ResultadoLaboratorio::TIPOS_ARCHIVO, true) ?: 'application/octet-stream';
        header('Content-Type: ' . $tipo);
        header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\-]/', '_', (string) $archivo['archivo_nombre']) . '"');
        header('Content-Length: ' . strlen($archivo['archivo']));
        echo $archivo['archivo'];
        exit;
    }

    /** EN_PROCESO, ENTREGADO y CANCELADO: mismo esquema (permiso M, estado de origen válido, bitácora). */
    private function cambiarEstado(string $nuevo, array $desde, string $mensaje): void
    {
        Sesion::exigirPermiso(self::MODULO, 'M');
        $examen = $this->obtenerExamen((int) ($_POST['id'] ?? 0), $desde);
        Conexion::transaccion(function () use ($examen, $nuevo) {
            (new SolicitudExamen())->cambiarEstado((int) $examen['id'], $nuevo);
            (new Bitacora())->registrarEvento($this->idUsuario(), 'EXAMEN_' . $nuevo,
                                              $examen['examen'] . ' del paciente ' . $examen['nro_expediente'] . ': ' . $examen['estado'] . ' -> ' . $nuevo,
                                              $this->ip(), 'solicitud_examen', (int) $examen['id']);
        });
        Sesion::flash('ok', $mensaje);
        $this->redirigir('laboratorio&paciente_id=' . $examen['paciente_id']);
    }

    private function mostrarFormularioSolicitud(array $paciente, array $d, array $e): void
    {
        $this->vista('Laboratorio/IUSolicitudExamen', [
            'paciente' => $paciente, 'citas' => (new Cita())->listarParaExamen((int) $paciente['persona_id']),
            'tipos' => (new TipoExamen())->listarActivos(), 'solicitud' => $d, 'errores' => $e,
        ]);
    }

    private function leerResultado(int $solicitudId): array
    {
        $d = ['solicitud_examen_id' => $solicitudId, 'resultado' => $this->texto('resultado') ?: null,
              'archivo_nombre' => null, 'tipo_archivo' => null, 'tamano_bytes' => null, 'archivo' => null];
        $e = [];
        $subida = $_FILES['archivo'] ?? null;
        if ($subida && $subida['error'] !== UPLOAD_ERR_NO_FILE) {
            $mime = $subida['error'] === UPLOAD_ERR_OK ? mime_content_type($subida['tmp_name']) : '';
            if ($subida['error'] !== UPLOAD_ERR_OK || $subida['size'] > self::MAX_BYTES) {
                $e['archivo'] = 'El archivo no se pudo subir o supera los 2 MB.';
            } elseif (!isset(ResultadoLaboratorio::TIPOS_ARCHIVO[$mime])) {
                $e['archivo'] = 'Solo se aceptan archivos PDF, JPG o PNG.';
            } else {
                $d['archivo'] = file_get_contents($subida['tmp_name']);
                $d['archivo_nombre'] = mb_substr(basename($subida['name']), 0, 150);
                $d['tipo_archivo'] = ResultadoLaboratorio::TIPOS_ARCHIVO[$mime];
                $d['tamano_bytes'] = (int) $subida['size'];
            }
        }
        if ($d['resultado'] === null && $d['archivo'] === null && !isset($e['archivo'])) {
            $e['resultado'] = 'Escriba el resultado o adjunte el archivo del laboratorio.';
        }
        return [$d, $e];
    }

    private function obtenerExamen(int $id, array $estadosValidos): array
    {
        $examen = (new SolicitudExamen())->obtenerPorId($id);
        if (!$examen || !in_array($examen['estado'], $estadosValidos, true)) {
            Sesion::flash('error', 'El examen no existe o su estado no permite esta acción.');
            $this->redirigir('laboratorio' . ($examen ? '&paciente_id=' . $examen['paciente_id'] : ''));
        }
        return $examen;
    }

    private function obtenerPaciente(int $id): array
    {
        $paciente = (new Paciente())->obtenerPorId($id);
        if (!$paciente) {
            Sesion::flash('error', 'El paciente no existe.');
            $this->redirigir('laboratorio');
        }
        return $paciente;
    }
}
