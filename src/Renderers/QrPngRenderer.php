<?php

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

use Laika\Barcode\Exceptions\BarcodeException;

/**
 * Renders a bool[][] QR matrix as raw PNG bytes (requires GD extension).
 *
 * Options:
 *  - module_size        int          Pixels per module (default: 8)
 *  - margin             int          Quiet-zone modules, min 4 per spec (default: 4)
 *  - color              string|int[] Dark module colour — hex or [r,g,b] (default: #000000)
 *  - bg                 string|int[] Background colour — hex, [r,g,b], or null = transparent (default: #ffffff)
 *
 * Watermark options (center overlay — use EC='H'):
 *  - watermark_text     string  Text in the center box
 *  - watermark_image    string  File path or data URI of logo image
 *  - watermark_size     float   Fraction of QR size (0.05–0.30, default: 0.25)
 *  - watermark_bg       string  Box background colour (default: #ffffff)
 *  - watermark_color    string  Text colour (default: #000000)
 *  - watermark_font     int     GD built-in font (1–5), 0 = auto (default: 0)
 *  - watermark_padding  int     Padding inside box in px (default: 6)
 */
class QrPngRenderer
{
    private const DEFAULTS = [
        'module_size'       => 8,
        'margin'            => 4,
        'color'             => '#000000',
        'bg'                => '#ffffff',
        'watermark_text'    => '',
        'watermark_image'   => '',
        'watermark_size'    => 0.25,
        'watermark_bg'      => '#ffffff',
        'watermark_color'   => '#000000',
        'watermark_font'    => 0,
        'watermark_padding' => 6,
    ];

    /**
     * @param  bool[][]              $matrix  QR matrix (true = dark)
     * @param  array<string, mixed>  $options
     * @return string                Raw PNG bytes
     *
     * @throws BarcodeException
     */
    public function render(array $matrix, array $options = []): string
    {
        if (!extension_loaded('gd')) {
            throw new BarcodeException('The GD extension is required for PNG rendering.');
        }

        $opt = array_merge(self::DEFAULTS, $options);

        $modSize = max(1, (int) $opt['module_size']);
        $margin  = max(0, (int) $opt['margin']);

        $qrSize = count($matrix);
        $fullPx = ($qrSize + 2 * $margin) * $modSize;

        // --- Create canvas ---
        $img = imagecreatetruecolor($fullPx, $fullPx)
            ?: throw new BarcodeException('Failed to create GD image.');

        $bgRgb  = $this->parseColor($opt['bg']);
        $barRgb = $this->parseColor($opt['color']);

        if ($bgRgb === null) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $bgAlloc = imagecolorallocatealpha($img, 0, 0, 0, 127);
        } else {
            $bgAlloc = imagecolorallocate($img, ...$bgRgb);
        }

        $barAlloc = imagecolorallocate($img, ...$barRgb);

        if ($bgAlloc === false || $barAlloc === false) {
            throw new BarcodeException('Failed to allocate GD colours.');
        }

        imagefill($img, 0, 0, $bgAlloc);

        // --- Draw modules ---
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (!$dark) {
                    continue;
                }
                $x = ($c + $margin) * $modSize;
                $y = ($r + $margin) * $modSize;
                imagefilledrectangle($img, $x, $y, $x + $modSize - 1, $y + $modSize - 1, $barAlloc);
            }
        }

        // --- Watermark ---
        $this->applyWatermark($img, $opt, $fullPx);

        // --- Capture PNG ---
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png ?: throw new BarcodeException('Failed to capture PNG output.');
    }

    // -------------------------------------------------------------------------
    // Watermark
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $opt */
    private function applyWatermark(\GdImage $img, array $opt, int $fullPx): void
    {
        $hasText  = trim((string) $opt['watermark_text']) !== '';
        $hasImage = trim((string) $opt['watermark_image']) !== '';

        if (!$hasText && !$hasImage) {
            return;
        }

        $fraction = min(0.30, max(0.05, (float) $opt['watermark_size']));
        $wmPx     = (int) round($fullPx * $fraction);
        $cx       = (int) round($fullPx / 2);
        $cy       = (int) round($fullPx / 2);
        $x        = $cx - intdiv($wmPx, 2);
        $y        = $cy - intdiv($wmPx, 2);
        $padding  = (int) $opt['watermark_padding'];

        // Background box
        $wmBgRgb = $this->parseColor((string) $opt['watermark_bg']) ?? [255, 255, 255];
        $wmBgAlloc = imagecolorallocate($img, ...$wmBgRgb);
        if ($wmBgAlloc !== false) {
            imagefilledrectangle($img, $x, $y, $x + $wmPx - 1, $y + $wmPx - 1, $wmBgAlloc);
        }

        // Image watermark
        if ($hasImage) {
            $this->drawImageWatermark(
                $img,
                (string) $opt['watermark_image'],
                $x + $padding,
                $y + $padding,
                $wmPx - 2 * $padding
            );
        }

        // Text watermark
        if ($hasText) {
            $wmColorRgb = $this->parseColor((string) $opt['watermark_color']) ?? [0, 0, 0];
            $wmColor    = imagecolorallocate($img, ...$wmColorRgb);

            if ($wmColor === false) {
                return;
            }

            $gdFont = (int) $opt['watermark_font'];
            if ($gdFont < 1 || $gdFont > 5) {
                // Auto-pick font size that fits in the box
                $available = $wmPx - 2 * $padding;
                $text      = (string) $opt['watermark_text'];
                $gdFont    = 1;
                for ($f = 5; $f >= 1; $f--) {
                    if (imagefontwidth($f) * strlen($text) <= $available
                        && imagefontheight($f) <= $available) {
                        $gdFont = $f;
                        break;
                    }
                }
            }

            $text    = (string) $opt['watermark_text'];
            $textW   = imagefontwidth($gdFont) * strlen($text);
            $textH   = imagefontheight($gdFont);
            $textY   = $hasImage
                ? ($y + $wmPx - $padding - $textH)
                : ($cy - intdiv($textH, 2));
            $textX   = $cx - intdiv($textW, 2);

            imagestring($img, $gdFont, $textX, $textY, $text, $wmColor);
        }
    }

    private function drawImageWatermark(\GdImage $img, string $src, int $x, int $y, int $size): void
    {
        // Decode data URI
        if (str_starts_with($src, 'data:')) {
            $comma  = strpos($src, ',');
            if ($comma === false) {
                return;
            }
            $b64  = substr($src, $comma + 1);
            $data = base64_decode($b64, true);
            if ($data === false) {
                return;
            }
            $logo = imagecreatefromstring($data) ?: null;
        } elseif (file_exists($src)) {
            $logo = imagecreatefromstring((string) file_get_contents($src)) ?: null;
        } else {
            return;
        }

        if ($logo === null) {
            return;
        }

        $lw = imagesx($logo);
        $lh = imagesy($logo);

        imagecopyresampled($img, $logo, $x, $y, 0, 0, $size, $size, $lw, $lh);
        imagedestroy($logo);
    }

    // -------------------------------------------------------------------------
    // Colour parsing
    // -------------------------------------------------------------------------

    /** @return int[]|null  [r, g, b] or null = transparent */
    private function parseColor(mixed $color): ?array
    {
        if ($color === null) {
            return null;
        }

        if (is_array($color)) {
            return [(int) $color[0], (int) $color[1], (int) $color[2]];
        }

        $hex = ltrim((string) $color, '#');
        if (strlen($hex) === 6) {
            return [
                (int) hexdec(substr($hex, 0, 2)),
                (int) hexdec(substr($hex, 2, 2)),
                (int) hexdec(substr($hex, 4, 2)),
            ];
        }

        return [0, 0, 0];
    }
}
