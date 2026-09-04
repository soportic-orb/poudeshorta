<?php
declare(strict_types=1);

namespace App\Services;

use FPDF;

/**
 * Base de tots els PDF de la plataforma.
 * Afegeix a FPDF: text UTF-8, colors hexadecimals i rectangles amb cantonades rodones.
 */
class Pdf extends FPDF
{
    /** Converteix UTF-8 a la codificació de les fonts bàsiques de FPDF (CP1252). */
    public function t(?string $text): string
    {
        $text = (string) $text;
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT', $text);
        return $converted === false ? $text : $converted;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    public function fillHex(string $hex): void
    {
        [$r, $g, $b] = self::rgb($hex);
        $this->SetFillColor($r, $g, $b);
    }

    public function textHex(string $hex): void
    {
        [$r, $g, $b] = self::rgb($hex);
        $this->SetTextColor($r, $g, $b);
    }

    public function drawHex(string $hex): void
    {
        [$r, $g, $b] = self::rgb($hex);
        $this->SetDrawColor($r, $g, $b);
    }

    /**
     * Rectangle amb cantonades arrodonides.
     *
     * @param string $style 'D' contorn, 'F' emplenat, 'DF' tots dos.
     */
    public function roundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'F'): void
    {
        $op = match (strtoupper($style)) {
            'F'           => 'f',
            'FD', 'DF'    => 'B',
            default       => 'S',
        };
        $k = $this->k;
        $hp = $this->h;
        $mid = $r * (4 / 3) * (sqrt(2) - 1); // punt de control de Bézier per a un quart de cercle

        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->arcTo($xc + $mid, $yc - $r, $xc + $r, $yc - $mid, $xc + $r, $yc);

        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->arcTo($xc + $r, $yc + $mid, $xc + $mid, $yc + $r, $xc, $yc + $r);

        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->arcTo($xc - $mid, $yc + $r, $xc - $r, $yc + $mid, $xc - $r, $yc);

        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->arcTo($xc - $r, $yc - $mid, $xc - $mid, $yc - $r, $xc, $yc - $r);

        $this->_out($op);
    }

    private function arcTo(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $k = $this->k;
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1 * $k, ($h - $y1) * $k,
            $x2 * $k, ($h - $y2) * $k,
            $x3 * $k, ($h - $y3) * $k
        ));
    }

    /** Línia de punts, per a la marca de retallar. */
    public function dashedLine(float $x1, float $y1, float $x2, float $y2, float $dash = 1.5, float $gap = 1.5): void
    {
        $this->_out(sprintf('[%.2F %.2F] 0 d', $dash * $this->k, $gap * $this->k));
        $this->Line($x1, $y1, $x2, $y2);
        $this->_out('[] 0 d');
    }

    /** Escriu text truncant-lo si no cap a l'amplada indicada. */
    public function fitCell(float $w, float $h, string $text, float $maxFontSize, float $minFontSize = 7.0, string $align = 'L'): void
    {
        $size = $maxFontSize;
        $converted = $this->t($text);
        while ($size > $minFontSize && $this->GetStringWidth($converted) > $w - 1) {
            $size -= 0.5;
            $this->SetFontSize($size);
        }
        if ($this->GetStringWidth($converted) > $w - 1) {
            while ($converted !== '' && $this->GetStringWidth($converted . '...') > $w - 1) {
                $converted = substr($converted, 0, -1);
            }
            $converted .= '...';
        }
        $this->Cell($w, $h, $converted, 0, 0, $align);
    }
}
