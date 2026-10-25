<?php
namespace App\Servicios;

/** Generador mínimo de PDF de una página (texto con fuente Helvetica), sin librerías. Usado para el comprobante de pago. */
final class PdfSimple
{
    private array $ops = [];

    private static function codificar(string $t): string
    {
        $t = mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    }

    public function texto(float $x, float $y, string $texto, int $tam = 11, bool $negrita = false): self
    {
        $this->ops[] = sprintf("BT /%s %d Tf %.1f %.1f Td (%s) Tj ET", $negrita ? 'F2' : 'F1', $tam, $x, $y, self::codificar($texto));
        return $this;
    }

    public function linea(float $x1, float $y1, float $x2, float $y2): self
    {
        $this->ops[] = sprintf("0.6 w %.1f %.1f m %.1f %.1f l S", $x1, $y1, $x2, $y2);
        return $this;
    }

    public function generar(): string
    {
        $contenido = implode("\n", $this->ops);
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            6 => "<< /Length " . strlen($contenido) . " >>\nstream\n$contenido\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $pos = [];
        foreach ($objetos as $n => $o) {
            $pos[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
        foreach ($pos as $p) {
            $pdf .= sprintf("%010d 00000 n \n", $p);
        }
        return $pdf . "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
