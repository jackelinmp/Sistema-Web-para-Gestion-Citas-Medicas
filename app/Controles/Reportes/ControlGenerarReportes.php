<?php
namespace App\Controles\Reportes;

use App\Entidades\Bitacora;
use App\Entidades\Cita;
use App\Entidades\Especialidad;
use App\Entidades\Jornada;
use App\Entidades\Medico;
use App\Entidades\Paciente;
use App\Entidades\Pago;
use App\Nucleo\ControlBase;
use App\Nucleo\Sesion;
use App\Servicios\PdfSimple;

/**
 * «control» ControlGenerarReportes — CU18 Reportes operativos (RF13).
 * Interfaces: IUReportes.  Entidades: Cita, Paciente, Pago, Jornada, Medico, Especialidad, Bitacora.  Servicio: PdfSimple.
 * Reportes de un periodo (máximo un año), con totales del periodo:
 *  - Citas: por fecha, médico, especialidad y estado (y la agenda diaria de un médico eligiendo un solo día).
 *  - Pacientes registrados.
 *  - Productividad por médico, ingresos diarios, ausentismo por especialidad y carga horaria.
 * Se ven en pantalla y se descargan en Excel (CSV) o en PDF. Solo cuentan como ingreso los pagos PAGADO.
 */
final class ControlGenerarReportes extends ControlBase
{
    private const MODULO = 'REPORTES';
    private const TIPOS = ['citas' => 'Citas', 'pacientes' => 'Pacientes registrados', 'productividad' => 'Productividad por médico',
                           'ingresos' => 'Ingresos diarios', 'ausentismo' => 'Ausentismo por especialidad',
                           'carga' => 'Carga horaria y ausencias por médico'];
    private const ESTADOS = ['PROGRAMADA', 'CONFIRMADA', 'ATENDIDA', 'CANCELADA', 'INASISTENCIA'];

    /** Encabezados de cada reporte para la descarga (columna de la consulta => título). */
    private const COLUMNAS = [
        'citas'         => ['fecha' => 'Fecha', 'hora' => 'Hora', 'codigo' => 'Código', 'paciente' => 'Paciente', 'paciente_ci' => 'CI',
                            'medico' => 'Médico', 'especialidad' => 'Especialidad', 'estado' => 'Estado'],
        'pacientes'     => ['expediente' => 'Expediente', 'paciente' => 'Paciente', 'ci' => 'CI', 'edad' => 'Edad', 'sexo' => 'Sexo',
                            'registrado' => 'Registrado', 'citas' => 'Citas'],
        'productividad' => ['medico' => 'Médico', 'citas' => 'Citas', 'atendidas' => 'Atendidas', 'canceladas' => 'Canceladas',
                            'inasistencias' => 'Inasistencias', 'pct_inasistencia' => '% inasistencia', 'ingresos' => 'Ingresos (Bs)'],
        'ingresos'      => ['dia' => 'Día', 'metodo' => 'Método', 'pagos' => 'Pagos', 'total' => 'Total (Bs)'],
        'ausentismo'    => ['especialidad' => 'Especialidad', 'citas_vencidas' => 'Citas que debían ocurrir',
                            'inasistencias' => 'Inasistencias', 'pct_inasistencia' => '% inasistencia'],
        'carga'         => ['medico' => 'Médico', 'especialidades' => 'Especialidades', 'horas_jornada' => 'Horas de jornada',
                            'citas' => 'Citas', 'horas_citas' => 'Horas ocupadas', 'pct_ocupacion' => '% ocupación', 'dias_ausente' => 'Días ausente'],
    ];

    public function mostrarReportes(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $f = $this->leerFiltros();
        $error = $this->validarPeriodo($f['desde'], $f['hasta']);
        $this->vista('Reportes/IUReportes', [
            'filtros'        => $f,
            'tipos'          => self::TIPOS,
            'estados'        => self::ESTADOS,
            'medicos'        => (new Medico())->listarActivos(),
            'especialidades' => (new Especialidad())->listarActivas(),
            'error'          => $error,
            'resumen'        => $error ? null : (new Cita())->resumirPeriodo($f['desde'], $f['hasta']),
            'filas'          => $error ? [] : $this->consultar($f),
        ]);
    }

    /** Descarga el reporte en pantalla: ?formato=excel (CSV que abre Excel) o ?formato=pdf. */
    public function exportarReporte(): void
    {
        Sesion::exigirPermiso(self::MODULO, 'C');
        $f = $this->leerFiltros();
        if ($error = $this->validarPeriodo($f['desde'], $f['hasta'])) {
            Sesion::flash('error', $error);
            $this->redirigir('reportes');
        }
        $formato = ($_GET['formato'] ?? '') === 'pdf' ? 'pdf' : 'excel';
        $filas = $this->consultar($f);
        (new Bitacora())->registrarEvento($this->idUsuario(), 'EXPORTAR_REPORTE',
            self::TIPOS[$f['tipo']] . " ($formato) del {$f['desde']} al {$f['hasta']}", $this->ip());
        $archivo = 'reporte_' . $f['tipo'] . '_' . $f['desde'] . '_' . $f['hasta'];
        $formato === 'pdf' ? $this->exportarPdf($f, $filas, $archivo) : $this->exportarExcel($f, $filas, $archivo);
        exit;
    }

    /** CSV con separador «;» y UTF-8 con BOM: Excel lo abre con tildes y columnas separadas. */
    private function exportarExcel(array $f, array $filas, string $archivo): void
    {
        $columnas = self::COLUMNAS[$f['tipo']];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivo . '.csv"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, array_values($columnas), ';');
        foreach ($filas as $fila) {
            fputcsv($salida, array_map(fn($c) => $fila[$c] ?? '', array_keys($columnas)), ';');
        }
        if ($total = $this->totalDelReporte($f['tipo'], $filas)) {
            fputcsv($salida, [$total], ';');
        }
        fclose($salida);
    }

    /** PDF A4 con título, periodo, filtros, tabla (en varias páginas si hace falta) y total. */
    private function exportarPdf(array $f, array $filas, string $archivo): void
    {
        $columnas = self::COLUMNAS[$f['tipo']];
        $tam = count($columnas) > 6 ? 7 : 8;
        $anchoUtil = PdfSimple::ANCHO - 80;
        // Ancho de cada columna según su contenido más largo, repartido en el ancho de la hoja
        $pesos = [];
        foreach ($columnas as $c => $titulo) {
            $largo = mb_strlen($titulo);
            foreach ($filas as $fila) {
                $largo = max($largo, mb_strlen((string) ($fila[$c] ?? '')));
            }
            $pesos[$c] = min($largo, 34) + 2;
        }
        $suma = array_sum($pesos);
        $x = 40;
        $posiciones = [];
        foreach ($pesos as $c => $p) {
            $posiciones[$c] = [$x, $anchoUtil * $p / $suma];
            $x += $anchoUtil * $p / $suma;
        }
        $recortar = function (string $t, float $ancho) use ($tam): string {
            $max = max(3, (int) floor($ancho / ($tam * 0.5)) - 1);
            return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
        };

        $pdf = new PdfSimple();
        $pagina = 1;
        $encabezado = function () use ($pdf, $f, $columnas, $posiciones, $recortar, $tam, &$pagina): float {
            $pdf->texto(40, 800, 'Clínica Vida Salud', 14, true)
                ->texto(40, 783, self::TIPOS[$f['tipo']], 12, true)
                ->texto(40, 768, 'Periodo: ' . date('d/m/Y', strtotime($f['desde'])) . ' al ' . date('d/m/Y', strtotime($f['hasta']))
                                 . $this->describirFiltros($f), 9)
                ->texto(430, 800, 'Emitido: ' . date('d/m/Y H:i'), 8)
                ->texto(430, 788, 'Página ' . $pagina, 8)
                ->linea(40, 760, PdfSimple::ANCHO - 40, 760);
            foreach ($columnas as $c => $titulo) {
                $pdf->texto($posiciones[$c][0], 746, $recortar($titulo, $posiciones[$c][1]), $tam, true);
            }
            $pdf->linea(40, 740, PdfSimple::ANCHO - 40, 740);
            return 728;
        };
        $y = $encabezado();
        foreach ($filas as $fila) {
            if ($y < 50) {
                $pdf->nuevaPagina();
                $pagina++;
                $y = $encabezado();
            }
            foreach ($columnas as $c => $titulo) {
                $pdf->texto($posiciones[$c][0], $y, $recortar((string) ($fila[$c] ?? ''), $posiciones[$c][1]), $tam);
            }
            $y -= $tam + 5;
        }
        if (!$filas) {
            $pdf->texto(40, $y, 'No hay datos en el periodo elegido.', 9);
            $y -= 14;
        }
        $pdf->linea(40, $y + 4, PdfSimple::ANCHO - 40, $y + 4);
        $pdf->texto(40, $y - 10, $this->totalDelReporte($f['tipo'], $filas) ?: 'Registros: ' . count($filas), 9, true);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $archivo . '.pdf"');
        echo $pdf->generar();
    }

    private function consultar(array $f): array
    {
        return match ($f['tipo']) {
            'citas'      => (new Cita())->listarParaReporte($f['desde'], $f['hasta'], $f['estado'], $f['medico_id'], $f['especialidad_id']),
            'pacientes'  => (new Paciente())->listarRegistrados($f['desde'], $f['hasta']),
            'ingresos'   => (new Pago())->reportarIngresos($f['desde'], $f['hasta']),
            'ausentismo' => (new Cita())->reportarAusentismo($f['desde'], $f['hasta']),
            'carga'      => (new Jornada())->reportarCargaHoraria($f['desde'], $f['hasta']),
            default      => (new Cita())->reportarProductividad($f['desde'], $f['hasta']),
        };
    }

    /** Línea de totales que acompaña a la descarga (vacía si el reporte no tiene un total que sumar). */
    private function totalDelReporte(string $tipo, array $filas): string
    {
        return match ($tipo) {
            'ingresos'      => 'Total del periodo: Bs ' . number_format((float) array_sum(array_column($filas, 'total')), 2, ',', '.'),
            'productividad' => 'Total de citas: ' . array_sum(array_column($filas, 'citas'))
                             . ' · Ingresos: Bs ' . number_format((float) array_sum(array_column($filas, 'ingresos')), 2, ',', '.'),
            'citas'         => 'Total de citas: ' . count($filas) . $this->contarEstados($filas),
            'pacientes'     => 'Total de pacientes: ' . count($filas),
            default         => '',
        };
    }

    /** « · Atendida: 3 · Cancelada: 1 …» para el pie del reporte de citas. */
    private function contarEstados(array $filas): string
    {
        $cuenta = array_count_values(array_column($filas, 'estado'));
        $texto = '';
        foreach (self::ESTADOS as $e) {
            if (!empty($cuenta[$e])) {
                $texto .= ' · ' . ucfirst(strtolower($e)) . ': ' . $cuenta[$e];
            }
        }
        return $texto;
    }

    private function describirFiltros(array $f): string
    {
        if ($f['tipo'] !== 'citas') {
            return '';
        }
        $partes = [];
        if ($f['estado']) {
            $partes[] = 'estado ' . ucfirst(strtolower($f['estado']));
        }
        if ($f['medico_id'] && ($m = (new Medico())->obtenerPorId($f['medico_id']))) {
            $partes[] = 'médico ' . $m['nombre'];
        }
        if ($f['especialidad_id'] && ($e = (new Especialidad())->obtenerPorId($f['especialidad_id']))) {
            $partes[] = $e['nombre'];
        }
        return $partes ? '  ·  ' . implode(' · ', $partes) : '';
    }

    /** Por defecto: el mes en curso, reporte de productividad. */
    private function leerFiltros(): array
    {
        $tipo = (string) ($_GET['tipo'] ?? 'productividad');
        $estado = (string) ($_GET['estado'] ?? '');
        return [
            'tipo'            => isset(self::TIPOS[$tipo]) ? $tipo : 'productividad',
            'desde'           => (string) ($_GET['desde'] ?? date('Y-m-01')),
            'hasta'           => (string) ($_GET['hasta'] ?? date('Y-m-d')),
            'estado'          => in_array($estado, self::ESTADOS, true) ? $estado : '',
            'medico_id'       => (int) ($_GET['medico_id'] ?? 0) ?: null,
            'especialidad_id' => (int) ($_GET['especialidad_id'] ?? 0) ?: null,
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
