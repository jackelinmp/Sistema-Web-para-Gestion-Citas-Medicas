<?php
namespace App\Servicios;

/**
 * Generador mínimo de PDF (A4, texto Helvetica y líneas), sin librerías.
 * Lo usan el comprobante de pago (CU19/CU20, una página) y los reportes de CU18 (varias páginas).
 */
final class PdfSimple
{
    public const ANCHO = 595;
    public const ALTO = 842;

    /** @var string[][] operaciones de dibujo de cada página */
    private array $paginas = [[]];

    private static function codificar(string $t): string
    {
        $t = mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }

    public function texto(float $x, float $y, string $texto, int $tam = 11, bool $negrita = false): self
    {
        $this->paginas[array_key_last($this->paginas)][] =
            sprintf("BT /%s %d Tf %.1f %.1f Td (%s) Tj ET", $negrita ? 'F2' : 'F1', $tam, $x, $y, self::codificar($texto));
        return $this;
    }

    public function linea(float $x1, float $y1, float $x2, float $y2): self
    {
        $this->paginas[array_key_last($this->paginas)][] = sprintf("0.6 w %.1f %.1f m %.1f %.1f l S", $x1, $y1, $x2, $y2);
        return $this;
    }

    public function nuevaPagina(): self
    {
        $this->paginas[] = [];
        return $this;
    }

    /** Ancho aproximado de un texto en puntos (Helvetica ≈ 0,5 del tamaño por carácter). */
    public static function anchoTexto(string $texto, int $tam): float
    {
        return mb_strlen($texto) * $tam * 0.5;
    }

    public function generar(): string
    {
        $n = count($this->paginas);
        $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
                    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($this->paginas as $i => $ops) {
            $pagina = 5 + 2 * $i;
            $contenido = implode("\n", $ops);
            $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::ANCHO . ' ' . self::ALTO . '] '
                              . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($pagina + 1) . ' 0 R >>';
            $objetos[$pagina + 1] = "<< /Length " . strlen($contenido) . " >>\nstream\n$contenido\nendstream";
            $kids[] = "$pagina 0 R";
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        ksort($objetos);
        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($objetos as $num => $o) {
            $pos[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
        foreach ($pos as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        return $pdf . "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
