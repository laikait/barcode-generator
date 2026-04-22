<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

use Laika\Barcode\Interfaces\RendererInterface;

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
 *  - font_size          int    Data label font size in px (default: 12)
 *
 * Title / footer:
 *  - title              string Text printed above the barcode
 *  - title_size         int    Font size in px, 0 = auto (default: 0)
 *  - title_color        string Title colour (default: same as color)
 *  - title_align        string 'left' | 'center' | 'right' (default: 'center')
 *  - footer             string Text printed below the data label
 *  - footer_size        int    Font size in px, 0 = auto (default: 0)
 *  - footer_color       string Footer colour (default: same as color)
 *  - footer_align       string 'left' | 'center' | 'right' (default: 'center')
 *
 * Watermark:
 *  - watermark_text     string  Diagonal overlay text (e.g. 'SAMPLE', 'VOID')
 *  - watermark_color    string  Watermark text colour (default: #000000)
 *  - watermark_font     int     Font size in px, 0 = auto (default: 0)
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
        'title'              => '',
        'title_size'         => 0,
        'title_color'        => '',
        'title_align'        => 'center',
        'footer'             => '',
        'footer_size'        => 0,
        'footer_color'       => '',
        'footer_align'       => 'center',
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

        $title      = trim((string) $opt['title']);
        $footer     = trim((string) $opt['footer']);
        $titleSize  = (int) $opt['title_size'] ?: (int) round($fontSize * 1.2);
        $footerSize = (int) $opt['footer_size'] ?: $fontSize;
        $titleColor = htmlspecialchars($opt['title_color'] ?: $opt['color'], ENT_XML1);
        $footerColor= htmlspecialchars($opt['footer_color'] ?: $opt['color'], ENT_XML1);

        $totalModules = array_sum(array_column($bars, 'width'));
        $barcodeW     = $totalModules * $modW;
        $svgW         = $barcodeW + 2 * $margin;

        // Vertical layout calculation
        $titleH  = $title  !== '' ? $titleSize  + 6 : 0;
        $labelH  = $showLabel && $label !== '' ? $fontSize + 4 : 0;
        $footerH = $footer !== '' ? $footerSize + 6 : 0;
        $svgH    = $margin + $titleH + $barHeight + $labelH + $footerH + $margin;

        $barY = $margin + $titleH;

        // Background
        $bgRect = '';
        if ($bg !== '') {
            $bgColor = htmlspecialchars($bg, ENT_XML1);
            $bgRect  = sprintf('<rect width="%d" height="%d" fill="%s"/>', $svgW, $svgH, $bgColor);
        }

        // Title
        $titleEl = '';
        if ($title !== '') {
            $tx = $this->alignX($opt['title_align'], $svgW, $margin);
            $anchor = $this->textAnchor($opt['title_align']);
            $titleEl = sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-size="%d" font-weight="bold" '
                . 'text-anchor="%s" dominant-baseline="hanging" fill="%s">%s</text>',
                $tx, $margin, $titleSize, $anchor, $titleColor,
                htmlspecialchars($title, ENT_XML1)
            );
        }

        // Bars
        $rects = '';
        $x     = $margin;
        foreach ($bars as $bar) {
            $w = $bar['width'] * $modW;
            if ($bar['bar']) {
                $rects .= sprintf(
                    '<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>',
                    $x, $barY, $w, $barHeight, $color
                );
            }
            $x += $w;
        }

        // Data label
        $labelEl = '';
        if ($showLabel && $label !== '') {
            $ly      = $barY + $barHeight + $fontSize + 2;
            $lx      = $this->alignX($opt['title_align'], $svgW, $margin); // reuse center
            $labelEl = sprintf(
                '<text x="%d" y="%d" font-family="monospace" font-size="%d" text-anchor="middle" fill="%s">%s</text>',
                (int) round($svgW / 2), $ly, $fontSize, $color,
                htmlspecialchars($label, ENT_XML1)
            );
        }

        // Footer
        $footerEl = '';
        if ($footer !== '') {
            $fy     = $svgH - $margin - 2;
            $fx     = $this->alignX($opt['footer_align'], $svgW, $margin);
            $anchor = $this->textAnchor($opt['footer_align']);
            $footerEl = sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-size="%d" '
                . 'text-anchor="%s" dominant-baseline="auto" fill="%s">%s</text>',
                $fx, $fy, $footerSize, $anchor, $footerColor,
                htmlspecialchars($footer, ENT_XML1)
            );
        }

        // Watermark
        $watermarkEl = $this->buildWatermark($opt, $svgW, $svgH, $barY, $barHeight);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '%s%s%s%s%s%s</svg>',
            $svgW, $svgH, $svgW, $svgH,
            $bgRect, $titleEl, $rects, $watermarkEl, $labelEl, $footerEl
        );
    }

    // -------------------------------------------------------------------------

    private function buildWatermark(array $opt, int $svgW, int $svgH, int $barY, int $barHeight): string
    {
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
            $fontSize = max(10, (int) round($barHeight * 0.35));
        }

        $cx = (int) round($svgW / 2);
        $cy = match ($position) {
            'top'    => $barY + (int) round($barHeight * 0.25),
            'bottom' => $barY + (int) round($barHeight * 0.80),
            default  => $barY + (int) round($barHeight / 2),
        };

        return sprintf(
            '<text x="%d" y="%d" font-family="sans-serif" font-weight="bold" font-size="%d" '
            . 'text-anchor="middle" dominant-baseline="central" fill="%s" opacity="%.2f" '
            . 'transform="rotate(%d %d %d)">%s</text>',
            $cx, $cy, $fontSize, $wmColor, $opacity, $rotate, $cx, $cy,
            htmlspecialchars($text, ENT_XML1)
        );
    }

    private function alignX(string $align, int $svgW, int $margin): int
    {
        return match (strtolower($align)) {
            'left'  => $margin,
            'right' => $svgW - $margin,
            default => (int) round($svgW / 2),
        };
    }

    private function textAnchor(string $align): string
    {
        return match (strtolower($align)) {
            'left'  => 'start',
            'right' => 'end',
            default => 'middle',
        };
    }
}
