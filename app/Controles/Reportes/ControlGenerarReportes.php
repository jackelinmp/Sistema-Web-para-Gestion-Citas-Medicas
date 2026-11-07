<?php
namespace App\Controles\Reportes;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Jornada;
use App\Entidades\Pago;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;

/**
 * «control» ControlGenerarReportes — CU18 Reportes operativos.
 * Interfaces: IUReportes.  Entidades: Cita, Pago, Bitacora.
 * Tres reportes de un periodo (máximo un año): productividad por médico, ingresos diarios y
 * ausentismo por especialidad (lo alimenta CU17). Se ven en pantalla y se descargan en CSV (Excel).
 * Solo cuentan como ingreso los pagos PAGADO: los reembolsados y anulados no suman.
 */
final class ControlGenerarReportes extends ControlBase
{
    private const MODULO = 'REPORTES';
    private const TIPOS = ['productividad' => 'Productividad por médico', 'ingresos' => 'Ingresos diarios',
                           'ausentismo' => 'Ausentismo por especialidad', 'carga' => 'Carga horaria y ausencias por médico'];

    public function mostrarReportes(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $f = $this->leerFiltros();
        $error = $this->validarPeriodo($f['desde'], $f['hasta']);
        $this->vista('Reportes/IUReportes', [
            'filtros' => $f,
            'tipos'   => self::TIPOS,
            'error'   => $error,
            'resumen' => $error ? null : (new Cita())->resumirPeriodo($f['desde'], $f['hasta']),
            'filas'   => $error ? [] : $this->consultar($f),
        ]);
    }

    /** Descarga el reporte elegido en CSV (separador «;» y UTF-8 con BOM para que Excel muestre las tildes). */
    public function exportarReporte(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $f = $this->leerFiltros();
        if ($error = $this->validarPeriodo($f['desde'], $f['hasta'])) {
            Sesion::flash('error', $error);
            $this->redirigir('reportes');
        }
        $filas = $this->consultar($f);
        (new Bitacora())->registrarEvento($this->idUsuario(), 'EXPORTAR_REPORTE',
            self::TIPOS[$f['tipo']] . " del {$f['desde']} al {$f['hasta']}", $this->ip());
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reporte_' . $f['tipo'] . '_' . $f['desde'] . '_' . $f['hasta'] . '.csv"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        if ($filas) {
            fputcsv($salida, array_keys($filas[0]), ';');
            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ';');
            }
        }
        fclose($salida);
        exit;
    }

    private function consultar(array $f): array
    {
        return match ($f['tipo']) {
            'ingresos'   => (new Pago())->reportarIngresos($f['desde'], $f['hasta']),
            'ausentismo' => (new Cita())->reportarAusentismo($f['desde'], $f['hasta']),
            'carga'      => (new Jornada())->reportarCargaHoraria($f['desde'], $f['hasta']),
            default      => (new Cita())->reportarProductividad($f['desde'], $f['hasta']),
        };
    }

    /** Por defecto: el mes en curso, reporte de productividad. */
    private function leerFiltros(): array
    {
        $tipo = (string) ($_GET['tipo'] ?? 'productividad');
        return [
            'tipo'  => isset(self::TIPOS[$tipo]) ? $tipo : 'productividad',
            'desde' => (string) ($_GET['desde'] ?? date('Y-m-01')),
            'hasta' => (string) ($_GET['hasta'] ?? date('Y-m-d')),
        ];
    }

    private function validarPeriodo(string $desde, string $hasta): ?string
    {
        foreach ([$desde, $hasta] as $f) {
            $d = \DateTime::createFromFormat('Y-m-d', $f);
            if (!$d || $d->format('Y-m-d') !== $f) {
                return 'Seleccione fechas válidas.';
            }
        }
        if ($desde > $hasta) {
            return 'La fecha «desde» debe ser anterior o igual a la fecha «hasta».';
        }
        if (strtotime($hasta) - strtotime($desde) > 366 * 86400) {
            return 'El periodo no puede ser mayor a un año.';
        }
        return null;
    }
}
