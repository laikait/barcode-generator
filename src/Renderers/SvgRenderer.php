<?php

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

use Laika\Barcode\Contracts\RendererInterface;

/**
 * Renders a 1D barcode as an inline SVG string.
 *
 * Base options:
 *  - height             int    Bar height in px (default: 80)
 *  - module_width       int    Width of one narrow module in px (default: 2)
 *  - margin             int    Side/top/bottom margin in px (default: 10)
 *  - color              string Bar colour (default: #000000)
 *  - bg                 string Background colour, '' = transparent (default: #ffffff)
 *  - show_label         bool   Print the data label below bars (default: true)
 *  - font_size          int    Label font size in px (default: 12)
 *
 * Watermark options (decorative — placed above bars, never over them):
 *  - watermark_text     string  Overlay text (e.g. company name, 'VOID', 'SAMPLE')
 *  - watermark_color    string  Watermark text colour (default: rgba(0,0,0,0.15))
 *  - watermark_font     int     Watermark font size in px, 0 = auto (default: 0)
 *  - watermark_position string  'top' | 'center' | 'bottom' (default: 'center')
 *  - watermark_opacity  float   Opacity 0.0–1.0 (default: 0.15)
 *  - watermark_rotate   int     Rotation in degrees (default: -20)
 */
class SvgRenderer implements RendererInterface
{
    private const DEFAULTS = [
        'height'             => 80,
        'module_width'       => 2,
        'margin'             => 10,
        'color'              => '#000000',
        'bg'                 => '#ffffff',
        'show_label'         => true,
        'font_size'          => 12,
        'watermark_text'     => '',
        'watermark_color'    => '#000000',
        'watermark_font'     => 0,
        'watermark_position' => 'center',
        'watermark_opacity'  => 0.15,
        'watermark_rotate'   => -20,
    ];

    public function render(array $bars, string $label, array $options = []): string
    {
        $opt = array_merge(self::DEFAULTS, $options);

        $barHeight = (int) $opt['height'];
        $modW      = (int) $opt['module_width'];
        $margin    = (int) $opt['margin'];
        $color     = htmlspecialchars((string) $opt['color'], ENT_XML1);
        $bg        = (string) $opt['bg'];
        $showLabel = (bool) $opt['show_label'];
        $fontSize  = (int) $opt['font_size'];

        $totalModules = array_sum(array_column($bars, 'width'));
        $barcodeW     = $totalModules * $modW;
        $svgW         = $barcodeW + 2 * $margin;
        $svgH         = $barHeight + 2 * $margin + ($showLabel ? $fontSize + 4 : 0);

        // Background
        $bgRect = '';
        if ($bg !== '') {
            $bgColor = htmlspecialchars($bg, ENT_XML1);
            $bgRect  = sprintf('<rect width="%d" height="%d" fill="%s"/>', $svgW, $svgH, $bgColor);
        }

        // Bars
        $rects = '';
        $x     = $margin;
        foreach ($bars as $bar) {
            $w = $bar['width'] * $modW;
            if ($bar['bar']) {
                $rects .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>',
                    $x, $margin, $w, $barHeight, $color
                );
            }
            $x += $w;
        }

        // Data label
        $textEl = '';
        if ($showLabel && $label !== '') {
            $ty      = $margin + $barHeight + $fontSize + 2;
            $cx      = (int) round($svgW / 2);
            $escaped = htmlspecialchars($label, ENT_XML1);
            $textEl  = sprintf(
                '<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
                $cx, $ty, $fontSize, $color, $escaped
            );
        }

        // Watermark
        $watermarkEl = $this->buildWatermark($opt, $svgW, $svgH, $margin, $barHeight);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s%s%s%s</svg>',
            $svgW, $svgH, $svgW, $svgH,
            $bgRect, $rects, $watermarkEl, $textEl
        );
    }

    // -------------------------------------------------------------------------
    // Watermark
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $opt */
    private function buildWatermark(
        array $opt,
        int $svgW,
        int $svgH,
        int $margin,
        int $barHeight
    ): string {
        $text = trim((string) $opt['watermark_text']);
        if ($text === '') {
            return '';
        }

        $opacity  = min(1.0, max(0.0, (float) $opt['watermark_opacity']));
        $wmColor  = htmlspecialchars((string) $opt['watermark_color'], ENT_XML1);
        $rotate   = (int) $opt['watermark_rotate'];
        $position = strtolower((string) $opt['watermark_position']);

        $fontSize = (int) $opt['watermark_font'];
        if ($fontSize <= 0) {
            // Auto-size to roughly fill the bar width
            $fontSize = max(10, (int) round($barHeight * 0.35));
        }

        $cx = (int) round($svgW / 2);
        $cy = match ($position) {
            'top'    => $margin + (int) round($barHeight * 0.25),
            'bottom' => $margin + (int) round($barHeight * 0.80),
            default  => $margin + (int) round($barHeight / 2),
        };

        $escaped = htmlspecialchars($text, ENT_XML1);

        return sprintf(
            '<text x="%d" y="%d" font-family="sans-serif" font-weight="bold" font-size="%d" '
            . 'text-anchor="middle" dominant-baseline="central" fill="%s" opacity="%.2f" '
            . 'transform="rotate(%d %d %d)">%s</text>',
            $cx, $cy,
            $fontSize,
            $wmColor,
            $opacity,
            $rotate, $cx, $cy,
            $escaped
        );
    }
}
