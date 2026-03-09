<?php

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

use Laika\Barcode\Contracts\RendererInterface;
use Laika\Barcode\Exceptions\BarcodeException;

/**
 * Renders a barcode as a raw PNG binary string (requires GD extension).
 *
 * Options: same as SvgRenderer. Returns raw PNG bytes.
 */
class PngRenderer implements RendererInterface
{
    private const DEFAULTS = [
        'height'       => 80,
        'module_width' => 2,
        'margin'       => 10,
        'color'        => [0, 0, 0],       // RGB int array or hex string
        'bg'           => [255, 255, 255], // RGB int array or hex string, null = transparent
        'show_label'   => true,
        'font_size'    => 3,               // GD built-in font (1–5)
    ];

    public function render(array $bars, string $label, array $options = []): string
    {
        if (!extension_loaded('gd')) {
            throw new BarcodeException('The GD extension is required for PNG rendering.');
        }

        $opt = array_merge(self::DEFAULTS, $options);

        $barHeight  = (int) $opt['height'];
        $modW       = (int) $opt['module_width'];
        $margin     = (int) $opt['margin'];
        $gdFont     = min(5, max(1, (int) $opt['font_size']));
        $showLabel  = (bool) $opt['show_label'];

        $fh          = imagefontheight($gdFont);
        $totalModules = array_sum(array_column($bars, 'width'));
        $barcodeW    = $totalModules * $modW;
        $imgW        = $barcodeW + 2 * $margin;
        $imgH        = $barHeight + 2 * $margin + ($showLabel ? $fh + 4 : 0);

        $img = imagecreatetruecolor($imgW, $imgH);

        if ($img === false) {
            throw new BarcodeException('Failed to create GD image resource.');
        }

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
            throw new BarcodeException('Failed to allocate GD colors.');
        }

        imagefill($img, 0, 0, $bgAlloc);

        $x = $margin;
        foreach ($bars as $bar) {
            $w = $bar['width'] * $modW;
            if ($bar['bar']) {
                imagefilledrectangle($img, $x, $margin, $x + $w - 1, $margin + $barHeight - 1, $barAlloc);
            }
            $x += $w;
        }

        if ($showLabel && $label !== '') {
            $labelW = imagefontwidth($gdFont) * strlen($label);
            $lx     = (int) (($imgW - $labelW) / 2);
            $ly     = $margin + $barHeight + 4;
            imagestring($img, $gdFont, $lx, $ly, $label, $barAlloc);
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png ?: throw new BarcodeException('Failed to capture PNG output.');
    }

    /** @return int[]|null  [r, g, b] or null for transparent */
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
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }

        return [0, 0, 0];
    }
}
