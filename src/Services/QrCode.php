<?php
declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use RuntimeException;

/**
 * Genera codis QR en PNG a partir de la matriu de BaconQrCode, dibuixada amb GD.
 * Evitem dependències de renderitzat addicionals (Imagick / SVG).
 */
final class QrCode
{
    /**
     * @param int $size    Amplada aproximada del PNG en píxels.
     * @param int $margin  Zona de silenci, en mòduls.
     */
    public static function png(string $content, int $size = 480, int $margin = 2, string $foreground = '#000000', string $background = '#FFFFFF'): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('L\'extensió GD de PHP és necessària per generar codis QR.');
        }

        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M(), 'utf-8')->getMatrix();
        $modules = $matrix->getWidth();
        $total = $modules + $margin * 2;

        // Píxels enters per mòdul: així el QR queda nítid i sense antialiàsing.
        $scale = max(1, (int) floor($size / $total));
        $dimension = $total * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        if ($image === false) {
            throw new RuntimeException('No s\'ha pogut crear la imatge del codi QR.');
        }

        [$br, $bg, $bb] = self::hexToRgb($background);
        [$fr, $fg, $fb] = self::hexToRgb($foreground);
        $bgColor = imagecolorallocate($image, $br, $bg, $bb);
        $fgColor = imagecolorallocate($image, $fr, $fg, $fb);
        imagefilledrectangle($image, 0, 0, $dimension, $dimension, $bgColor);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $margin) * $scale;
                    $py = ($y + $margin) * $scale;
                    imagefilledrectangle($image, $px, $py, $px + $scale - 1, $py + $scale - 1, $fgColor);
                }
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** Desa el PNG a un fitxer temporal i en retorna el camí (per a FPDF). */
    public static function toTempFile(string $content, int $size = 480): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/qr_' . bin2hex(random_bytes(8)) . '.png';
        file_put_contents($path, self::png($content, $size));
        return $path;
    }

    public static function dataUri(string $content, int $size = 320): string
    {
        return 'data:image/png;base64,' . base64_encode(self::png($content, $size));
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
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
}
