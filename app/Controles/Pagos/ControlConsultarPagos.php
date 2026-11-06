<?php
namespace App\Controles\Pagos;

use App\Entidades\Pago;
use App\Entidades\VinculoFamiliar;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlConsultarPagos — CU20 Consultar Pagos y Comprobantes.   Ciclo 2 · P7 · Responsable: Jackeline
 * Interfaces: IUHistorialPagos, IUComprobante.  Entidades: Pago (con comprobante), VinculoFamiliar.
 * Solo lectura. Permiso PAGOS/C. El PACIENTE ve sus pagos y los de los familiares a su cargo (CU22).
 * El comprobante existe solo para pagos PAGADO (tabla comprobante, lo emite sp_registrar_pago).
 */
final class ControlConsultarPagos extends ControlBase
{
    private const MODULO = 'PAGOS';
    public const ESTADOS = ['PENDIENTE', 'PAGADO', 'ANULADO', 'REEMBOLSADO'];
    public const METODOS = ['EFECTIVO', 'TARJETA', 'QR', 'TRANSFERENCIA', 'STRIPE', 'SEGURO'];

    public function listarHistorial(): void
    {
        $elegido = (int) ($_GET['paciente_id'] ?? 0) ?: null;
        $pacienteId = $this->exigirAccesoPaciente(self::MODULO, $elegido);
        $filtros = [
            'paciente_id' => $elegido,
            'pacientes'   => $this->pacientesAMostrar($elegido, $pacienteId),
            'criterio'    => $this->esPaciente() ? '' : trim((string) ($_GET['criterio'] ?? '')),
            'estado'      => in_array($_GET['estado'] ?? '', self::ESTADOS, true) ? $_GET['estado'] : '',
            'metodo'      => in_array($_GET['metodo'] ?? '', self::METODOS, true) ? $_GET['metodo'] : '',
            'desde'       => $this->fechaDeFiltro('desde'),
            'hasta'       => $this->fechaDeFiltro('hasta'),
        ];
        $pagos = (new Pago())->listarHistorial($filtros);
        $this->vista('Pagos/IUHistorialPagos', [
            'pagos' => $pagos, 'totales' => $this->calcularTotales($pagos), 'filtros' => $filtros, 'esPaciente' => $this->esPaciente(),
        ]);
    }

    public function mostrarComprobante(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $pago = (new Pago())->obtenerPorCita((int) ($_GET['cita_id'] ?? 0));
        if (!$pago) {
            $this->redirigir('comprobantes');
        }
        $this->exigirAccesoPaciente(self::MODULO, (int) $pago['paciente_id']);   // el paciente: solo lo suyo o de su familia
        if ($pago['pago_estado'] !== 'PAGADO' || !$pago['nro_correlativo']) {
            Sesion::flash('error', 'La cita ' . $pago['codigo'] . ' no tiene un pago realizado: no tiene comprobante.');
            $this->redirigir('comprobantes');
        }
        $this->vista('Pagos/IUComprobante', ['pago' => $pago]);
    }

    /** Paciente sin otro elegido: él y sus familiares (el titular paga por ellos). Personal: uno o todos. */
    private function pacientesAMostrar(?int $elegido, ?int $pacienteId): ?array
    {
        if ($this->esPaciente() && !$elegido) {
            return array_merge([$pacienteId], array_column((new VinculoFamiliar())->listarFamiliares($pacienteId), 'familiar_id'));
        }
        return $pacienteId ? [$pacienteId] : null;
    }

    /** Suma de montos por estado de la lista mostrada. */
    private function calcularTotales(array $pagos): array
    {
        $totales = array_fill_keys(self::ESTADOS, 0.0);
        foreach ($pagos as $p) {
            $totales[$p['pago_estado']] = ($totales[$p['pago_estado']] ?? 0) + (float) $p['monto'];
        }
        return $totales;
    }
}
