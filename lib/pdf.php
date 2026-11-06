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
    /** @var list<array{data:string, w:int, h:int}> JPEG images used on the page (names /Im1, /Im2 ...) */
    private array $images = [];

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

    /**
     * Draw an SVG path (absolute M L H V Q C Z only, as our logo files use) as a filled shape.
     * $scale maps SVG units to points; ($ox, $oy) is where the SVG's (0,0) lands (top-left based, in points).
     * @param array{0:float,1:float,2:float} $rgb
     */
    public function svgPath(string $d, float $scale, float $ox, float $oy, array $rgb): void
    {
        $X = static fn(float $v): string => self::n($ox + $v * $scale);
        $Y = static fn(float $v): string => self::n(self::H - ($oy + $v * $scale));
        $ops = sprintf("%s %s %s rg\n", self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]));
        $cx = $cy = $sx = $sy = 0.0;
        foreach (self::parseSvgPath($d) as $seg) {
            switch ($seg[0]) {
                case 'M':
                    [$cx, $cy] = [$seg[1], $seg[2]];
                    [$sx, $sy] = [$cx, $cy];
                    $ops .= $X($cx) . ' ' . $Y($cy) . " m\n";
                    break;
                case 'L':
                    [$cx, $cy] = [$seg[1], $seg[2]];
                    $ops .= $X($cx) . ' ' . $Y($cy) . " l\n";
                    break;
                case 'Q': // quadratic → cubic
                    [$x1, $y1, $x, $y] = [$seg[1], $seg[2], $seg[3], $seg[4]];
                    $ops .= $X($cx + 2 / 3 * ($x1 - $cx)) . ' ' . $Y($cy + 2 / 3 * ($y1 - $cy)) . ' '
                        . $X($x + 2 / 3 * ($x1 - $x)) . ' ' . $Y($y + 2 / 3 * ($y1 - $y)) . ' ' . $X($x) . ' ' . $Y($y) . " c\n";
                    [$cx, $cy] = [$x, $y];
                    break;
                case 'C':
                    $ops .= $X($seg[1]) . ' ' . $Y($seg[2]) . ' ' . $X($seg[3]) . ' ' . $Y($seg[4]) . ' ' . $X($seg[5]) . ' ' . $Y($seg[6]) . " c\n";
                    [$cx, $cy] = [$seg[5], $seg[6]];
                    break;
                case 'Z':
                    $ops .= "h\n";
                    [$cx, $cy] = [$sx, $sy];
                    break;
            }
        }
        $this->ops .= $ops . "f\n";
    }

    /** Path data to segments: ['M',x,y] ['L',x,y] ['Q',x1,y1,x,y] ['C',x1,y1,x2,y2,x,y] ['Z']. H and V become L. @return list<list<string|float>> */
    public static function parseSvgPath(string $d): array
    {
        preg_match_all('/[MLHVQCZ]|-?\d*\.?\d+(?:e-?\d+)?/i', $d, $m);
        $t = $m[0];
        $out = [];
        $i = 0;
        $n = count($t);
        $cmd = '';
        $cx = $cy = 0.0;
        $num = static fn(int $k): float => (float) $t[$k];
        $count = ['M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'Q' => 4, 'C' => 6, 'Z' => 0];
        while ($i < $n) {
            if (ctype_alpha($t[$i])) {
                $cmd = strtoupper($t[$i]);
                $i++;
                if ($cmd === 'Z') {
                    $out[] = ['Z'];
                    continue;
                }
            }
            $need = $count[$cmd] ?? 0;
            if ($need === 0 || $i + $need > $n) {
                break;
            }
            if ($cmd === 'M') {
                [$cx, $cy] = [$num($i), $num($i + 1)];
                $out[] = ['M', $cx, $cy];
                $cmd = 'L'; // extra pairs after a move are lines
            } elseif ($cmd === 'L') {
                [$cx, $cy] = [$num($i), $num($i + 1)];
                $out[] = ['L', $cx, $cy];
            } elseif ($cmd === 'H') {
                $cx = $num($i);
                $out[] = ['L', $cx, $cy];
            } elseif ($cmd === 'V') {
                $cy = $num($i);
                $out[] = ['L', $cx, $cy];
            } elseif ($cmd === 'Q') {
                $out[] = ['Q', $num($i), $num($i + 1), $num($i + 2), $num($i + 3)];
                [$cx, $cy] = [$num($i + 2), $num($i + 3)];
            } else {
                $out[] = ['C', $num($i), $num($i + 1), $num($i + 2), $num($i + 3), $num($i + 4), $num($i + 5)];
                [$cx, $cy] = [$num($i + 4), $num($i + 5)];
            }
            $i += $need;
        }
        return $out;
    }

    /** Draw a JPEG file (bytes) into the box at ($x, $top) of $w by $h points. */
    public function jpeg(string $data, float $x, float $top, float $w, float $h): void
    {
        $info = @getimagesizefromstring($data);
        if (!$info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
            return;
        }
        $this->images[] = ['data' => $data, 'w' => (int) $info[0], 'h' => (int) $info[1]];
        $this->ops .= sprintf("q %s 0 0 %s %s %s cm /Im%d Do Q\n", self::n($w), self::n($h), self::n($x), self::n(self::H - $top - $h), count($this->images));
    }

    /** The finished PDF file as a string. */
    public function output(string $title = 'Document'): string
    {
        $objs = [];
        $imgRes = '';
        $next = 10; // objects 1-9 are fixed; images follow
        $imgObjs = [];
        foreach ($this->images as $k => $im) {
            $id = $next++;
            $imgRes .= '/Im' . ($k + 1) . " {$id} 0 R ";
            $imgObjs[$id] = "<< /Type /XObject /Subtype /Image /Width {$im['w']} /Height {$im['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($im['data']) . " >>\nstream\n" . $im['data'] . "\nendstream";
        }
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R /F3 7 0 R /F4 8 0 R >>'
            . ($imgRes !== '' ? ' /XObject << ' . $imgRes . '>>' : '') . ' >> >>';
        $objs[4] = "<< /Length " . strlen($this->ops) . " >>\nstream\n" . $this->ops . "endstream";
        foreach ([5 => 'Helvetica', 6 => 'Helvetica-Bold', 7 => 'Courier', 8 => 'Courier-Bold'] as $i => $f) {
            $objs[$i] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$f} /Encoding /WinAnsiEncoding >>";
        }
        $objs[9] = '<< /Title (' . self::enc($title) . ') /Producer (SIPAW) /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>';
        foreach ($imgObjs as $id => $body) {
            $objs[$id] = $body;
        }
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
