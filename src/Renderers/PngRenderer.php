<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);
 
namespace Laika\Barcode\Renderers;
 
use Laika\Barcode\Interfaces\RendererInterface;
use Laika\Barcode\Exceptions\BarcodeException;
 
/**
 * Renders a 1D barcode as raw PNG bytes (requires GD extension).
 *
 * Options: same as SvgRenderer.
 * - title / footer  string   Text above / below barcode
 * - title_size / footer_size  int  GD font 1–5, 0 = auto
 * - color / bg      string|int[]  Hex or [r,g,b], bg null = transparent
 */
class PngRenderer implements RendererInterface
{
    private const DEFAULTS = [
        'height'       => 80,
        'module_width' => 2,
        'margin'       => 10,
        'color'        => [0, 0, 0],
        'bg'           => [255, 255, 255],
        'show_label'   => true,
        'font_size'    => 3,
        'title'        => '',
        'title_size'   => 0,
        'title_color'  => null,
        'title_align'  => 'center',
        'footer'       => '',
        'footer_size'  => 0,
        'footer_color' => null,
        'footer_align' => 'center',
    ];
 
    public function render(array $bars, string $label, array $options = []): string
    {
        if (!extension_loaded('gd')) {
            throw new BarcodeException('The GD extension is required for PNG rendering.');
        }
 
        $opt = array_merge(self::DEFAULTS, $options);
 
        $barHeight = (int) $opt['height'];
        $modW      = (int) $opt['module_width'];
        $margin    = (int) $opt['margin'];
        $showLabel = (bool) $opt['show_label'];
        $gdFont    = min(5, max(1, (int) $opt['font_size']));
 
        $title     = trim((string) $opt['title']);
        $footer    = trim((string) $opt['footer']);
        $titleFont = $this->gdFont((int) $opt['title_size'], $gdFont + 1);
        $footFont  = $this->gdFont((int) $opt['footer_size'], $gdFont);
 
        $totalModules = array_sum(array_column($bars, 'width'));
        $barcodeW     = $totalModules * $modW;
        $imgW         = $barcodeW + 2 * $margin;
 
        $titleH  = $title  !== '' ? imagefontheight($titleFont) + 6 : 0;
        $labelH  = $showLabel && $label !== '' ? imagefontheight($gdFont) + 4 : 0;
        $footerH = $footer !== '' ? imagefontheight($footFont) + 6 : 0;
        $imgH    = $margin + $titleH + $barHeight + $labelH + $footerH + $margin;
 
        $barY = $margin + $titleH;
 
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
            $tx         = $this->alignX($opt['title_align'], $imgW, $margin, $tw);
            if ($titleAlloc !== false) {
                imagestring($img, $titleFont, $tx, $margin, $title, $titleAlloc);
            }
        }
 
        // Bars
        $x = $margin;
        foreach ($bars as $bar) {
            $w = $bar['width'] * $modW;
            if ($bar['bar']) {
                imagefilledrectangle($img, $x, $barY, $x + $w - 1, $barY + $barHeight - 1, $barAlloc);
            }
            $x += $w;
        }
 
        // Data label
        if ($showLabel && $label !== '') {
            $lw  = imagefontwidth($gdFont) * strlen($label);
            $lx  = intdiv($imgW - $lw, 2);
            $ly  = $barY + $barHeight + 4;
            imagestring($img, $gdFont, $lx, $ly, $label, $barAlloc);
        }
 
        // Footer
        if ($footer !== '') {
            $footerRgb   = $this->parseColor($opt['footer_color'] ?? $opt['color']) ?? $barRgb;
            $footerAlloc = imagecolorallocate($img, ...$footerRgb);
            $fw          = imagefontwidth($footFont) * strlen($footer);
            $fx          = $this->alignX($opt['footer_align'], $imgW, $margin, $fw);
            $fy          = $imgH - $margin - imagefontheight($footFont);
            if ($footerAlloc !== false) {
                imagestring($img, $footFont, $fx, $fy, $footer, $footerAlloc);
            }
        }
 
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        unset($img);
 
        return $png ?: throw new BarcodeException('Failed to capture PNG output.');
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
        if (is_array($color)) return [(int)$color[0], (int)$color[1], (int)$color[2]];
        $hex = ltrim((string) $color, '#');
        if (strlen($hex) === 6) {
            return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
        }
        return [0, 0, 0];
    }
}
