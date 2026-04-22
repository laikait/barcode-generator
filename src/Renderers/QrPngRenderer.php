<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);
 
namespace Laika\Barcode\Renderers;
 
use Laika\Barcode\Exceptions\BarcodeException;
 
/**
 * Renders a bool[][] QR matrix as raw PNG bytes (requires GD extension).
 *
 * Same options as QrSvgRenderer, plus:
 * - title_size / footer_size  GD font 1–5, 0 = auto
 * - color / bg   hex string or [r,g,b] array, bg null = transparent
 */
class QrPngRenderer
{
    private const DEFAULTS = [
        'module_size'       => 8,
        'margin'            => 4,
        'color'             => '#000000',
        'bg'                => '#ffffff',
        'title'             => '',
        'title_size'        => 0,
        'title_color'       => null,
        'title_align'       => 'center',
        'footer'            => '',
        'footer_size'       => 0,
        'footer_color'      => null,
        'footer_align'      => 'center',
        'watermark_text'    => '',
        'watermark_image'   => '',
        'watermark_size'    => 0.20,
        'watermark_bg'      => '#ffffff',
        'watermark_color'   => '#000000',
        'watermark_font'    => 0,
        'watermark_padding' => 6,
    ];
 
    /** @param bool[][] $matrix */
    public function render(array $matrix, array $options = []): string
    {
        if (!extension_loaded('gd')) {
            throw new BarcodeException('The GD extension is required for PNG rendering.');
        }
 
        $opt     = array_merge(self::DEFAULTS, $options);
        $modSize = max(1, (int) $opt['module_size']);
        $margin  = max(0, (int) $opt['margin']);
 
        $qrPx   = count($matrix) * $modSize;
        $qrFull = $qrPx + 2 * $margin * $modSize;
 
        $title      = trim((string) $opt['title']);
        $footer     = trim((string) $opt['footer']);
        $titleFont  = $this->gdFont((int) $opt['title_size'],  4);
        $footerFont = $this->gdFont((int) $opt['footer_size'], 3);
 
        $titleH  = $title  !== '' ? imagefontheight($titleFont)  + 8 : 0;
        $footerH = $footer !== '' ? imagefontheight($footerFont) + 8 : 0;
 
        $imgW      = $qrFull;
        $imgH      = $titleH + $qrFull + $footerH;
        $qrOffsetY = $titleH;
 
        $img = imagecreatetruecolor($imgW, $imgH)
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
 
        // Title
        if ($title !== '') {
            $titleRgb   = $this->parseColor($opt['title_color'] ?? $opt['color']) ?? $barRgb;
            $titleAlloc = imagecolorallocate($img, ...$titleRgb);
            $tw         = imagefontwidth($titleFont) * strlen($title);
            $tx         = $this->alignX($opt['title_align'], $imgW, $margin * $modSize, $tw);
            if ($titleAlloc !== false) {
                imagestring($img, $titleFont, $tx, 4, $title, $titleAlloc);
            }
        }
 
        // QR modules
        $qrMarginPx = $margin * $modSize;
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (!$dark) continue;
                $x = $qrMarginPx + $c * $modSize;
                $y = $qrOffsetY + $qrMarginPx + $r * $modSize;
                imagefilledrectangle($img, $x, $y, $x + $modSize - 1, $y + $modSize - 1, $barAlloc);
            }
        }
 
        // Center watermark
        $cx = (int) round($imgW / 2);
        $cy = $qrOffsetY + (int) round($qrFull / 2);
        $this->applyWatermark($img, $opt, $imgW, $qrFull, $cx, $cy);
 
        // Footer
        if ($footer !== '') {
            $footerRgb   = $this->parseColor($opt['footer_color'] ?? $opt['color']) ?? $barRgb;
            $footerAlloc = imagecolorallocate($img, ...$footerRgb);
            $fw          = imagefontwidth($footerFont) * strlen($footer);
            $fx          = $this->alignX($opt['footer_align'], $imgW, $margin * $modSize, $fw);
            $fy          = $titleH + $qrFull + 4;
            if ($footerAlloc !== false) {
                imagestring($img, $footerFont, $fx, $fy, $footer, $footerAlloc);
            }
        }
 
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        unset($img);
 
        return $png ?: throw new BarcodeException('Failed to capture PNG output.');
    }
 
    private function applyWatermark(\GdImage $img, array $opt, int $imgW, int $qrFull, int $cx, int $cy): void
    {
        $hasText  = trim((string) $opt['watermark_text'])  !== '';
        $hasImage = trim((string) $opt['watermark_image']) !== '';
        if (!$hasText && !$hasImage) return;
 
        $fraction  = min(0.30, max(0.05, (float) $opt['watermark_size']));
        $wmPx      = (int) round($qrFull * $fraction);
        $x         = $cx - intdiv($wmPx, 2);
        $y         = $cy - intdiv($wmPx, 2);
        $padding   = (int) $opt['watermark_padding'];
 
        $wmBgRgb   = $this->parseColor((string) $opt['watermark_bg']) ?? [255,255,255];
        $wmBgAlloc = imagecolorallocate($img, ...$wmBgRgb);
        if ($wmBgAlloc !== false) {
            imagefilledrectangle($img, $x, $y, $x + $wmPx - 1, $y + $wmPx - 1, $wmBgAlloc);
        }
 
        if ($hasImage) {
            $this->drawImageWatermark($img, (string) $opt['watermark_image'],
                $x + $padding, $y + $padding, $wmPx - 2 * $padding);
        }
 
        if ($hasText) {
            $wmColorRgb = $this->parseColor((string) $opt['watermark_color']) ?? [0,0,0];
            $wmColor    = imagecolorallocate($img, ...$wmColorRgb);
            if ($wmColor === false) return;
 
            $gdFont = (int) $opt['watermark_font'];
            if ($gdFont < 1 || $gdFont > 5) {
                $available = $wmPx - 2 * $padding;
                $text      = (string) $opt['watermark_text'];
                $gdFont    = 1;
                for ($f = 5; $f >= 1; $f--) {
                    if (imagefontwidth($f) * strlen($text) <= $available
                        && imagefontheight($f) <= $available) { $gdFont = $f; break; }
                }
            }
            $text  = (string) $opt['watermark_text'];
            $textW = imagefontwidth($gdFont) * strlen($text);
            $textH = imagefontheight($gdFont);
            $textY = $hasImage ? ($y + $wmPx - $padding - $textH) : ($cy - intdiv($textH, 2));
            $textX = $cx - intdiv($textW, 2);
            imagestring($img, $gdFont, $textX, $textY, $text, $wmColor);
        }
    }
 
    private function drawImageWatermark(\GdImage $img, string $src, int $x, int $y, int $size): void
    {
        if (str_starts_with($src, 'data:')) {
            $comma = strpos($src, ',');
            if ($comma === false) return;
            $data = base64_decode(substr($src, $comma + 1), true);
            if ($data === false) return;
            $logo = imagecreatefromstring($data) ?: null;
        } elseif (file_exists($src)) {
            $logo = imagecreatefromstring((string) file_get_contents($src)) ?: null;
        } else {
            return;
        }
        if ($logo === null) return;
        imagecopyresampled($img, $logo, $x, $y, 0, 0, $size, $size, imagesx($logo), imagesy($logo));
        unset($logo);
    }
 
    private function gdFont(int $requested, int $fallback): int
    {
        return $requested >= 1 && $requested <= 5 ? $requested : min(5, max(1, $fallback));
    }
 
    private function alignX(string $align, int $imgW, int $margin, int $textW): int
    {
        return match (strtolower($align)) {
            'left'  => $margin,
            'right' => $imgW - $margin - $textW,
            default => intdiv($imgW - $textW, 2),
        };
    }
 
    /** @return int[]|null */
    private function parseColor(mixed $color): ?array
    {
        if ($color === null) return null;
        if (is_array($color)) return [(int)$color[0],(int)$color[1],(int)$color[2]];
        $hex = ltrim((string)$color, '#');
        if (strlen($hex) === 6) {
            return [hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
        }
        return [0,0,0];
    }
}
