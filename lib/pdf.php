<?php
declare(strict_types=1);

/**
 * A very small PDF writer for one-page documents (invoices): A4, built-in Helvetica / Courier fonts, text, lines
 * and filled boxes. No library, no fonts to install. Text is converted to Windows-1252, so letters outside
 * Western European languages cannot be drawn (callers fall back to something drawable, like an email address).
 * Coordinates are in points from the TOP-left corner of the page (like a web page), which is easier to think in.
 */
final class RrpPdf
{
    public const W = 595.0;
    public const H = 842.0;
    private string $ops = '';

    /** Fonts: 'r' Helvetica, 'b' Helvetica-Bold, 'm' Courier, 'mb' Courier-Bold. */
    private const FONTS = ['r' => 'F1', 'b' => 'F2', 'm' => 'F3', 'mb' => 'F4'];

    /** Text safe for a PDF string: Windows-1252, escaped. Characters that cannot be shown become "?". */
    public static function enc(string $s): string
    {
        $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        $c = $c === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $c;
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], (string) $c);
    }

    /** True when the text can be drawn without "?" marks (so names in other scripts are not turned into ????). */
    public static function drawable(string $s): bool
    {
        $c = @iconv('UTF-8', 'Windows-1252//IGNORE', $s);
        return $c !== false && mb_strlen($s) === strlen($c) && !str_contains((string) @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s), '?');
    }

    /** Width of a Courier string (every character is 0.6 of the size wide): used to right-align amounts. */
    public static function monoWidth(string $s, float $size): float
    {
        return mb_strlen($s) * 0.6 * $size;
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** @param array{0:float,1:float,2:float} $rgb values 0..1 */
    public function text(float $x, float $top, string $s, float $size = 10, string $font = 'r', array $rgb = [0.1, 0.1, 0.12]): void
    {
        $y = self::H - $top - $size * 0.8;
        $this->ops .= sprintf("BT /%s %s Tf %s %s %s rg %s %s Td (%s) Tj ET\n", self::FONTS[$font] ?? 'F1', self::n($size),
            self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]), self::n($x), self::n($y), self::enc($s));
    }

    /** Right-aligned monospaced text whose right edge is at $xRight. */
    public function textRight(float $xRight, float $top, string $s, float $size = 10, bool $bold = false, array $rgb = [0.1, 0.1, 0.12]): void
    {
        $this->text($xRight - self::monoWidth($s, $size), $top, $s, $size, $bold ? 'mb' : 'm', $rgb);
    }

    public function line(float $x1, float $top1, float $x2, float $top2, float $width = 0.6, array $rgb = [0.8, 0.82, 0.86]): void
    {
        $this->ops .= sprintf("%s %s %s RG %s w %s %s m %s %s l S\n", self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]), self::n($width),
            self::n($x1), self::n(self::H - $top1), self::n($x2), self::n(self::H - $top2));
    }

    public function box(float $x, float $top, float $w, float $h, array $rgb): void
    {
        $this->ops .= sprintf("%s %s %s rg %s %s %s %s re f\n", self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]),
            self::n($x), self::n(self::H - $top - $h), self::n($w), self::n($h));
    }

    /** The finished PDF file as a string. */
    public function output(string $title = 'Document'): string
    {
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R /F4 8 0 R >> >> >>';
        $objs[4] = "<< /Length " . strlen($this->ops) . " >>\nstream\n" . $this->ops . "endstream";
        foreach ([5 => 'Helvetica', 6 => 'Helvetica-Bold', 7 => 'Courier', 8 => 'Courier-Bold'] as $i => $f) {
            $objs[$i] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$f} /Encoding /WinAnsiEncoding >>";
        }
        $objs[9] = '<< /Title (' . self::enc($title) . ') /Producer (SIPAW) /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>';
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i] = strlen($out);
            $out .= "{$i} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R /Info 9 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $out;
    }
}
