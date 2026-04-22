<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

/**
 * Renders a bool[][] QR matrix as an inline SVG string.
 *
 * Base options:
 *  - module_size        int     Pixels per module (default: 8)
 *  - margin             int     Quiet-zone modules (default: 4)
 *  - color              string  Dark module colour (default: #000000)
 *  - bg                 string  Background colour, '' = transparent (default: #ffffff)
 *
 * Title / footer:
 *  - title              string  Text printed above the QR code
 *  - title_size         int     Font size in px, 0 = auto (default: 0)
 *  - title_color        string  Title colour (default: same as color)
 *  - title_align        string  'left' | 'center' | 'right' (default: 'center')
 *  - footer             string  Text printed below the QR code
 *  - footer_size        int     Font size in px, 0 = auto (default: 0)
 *  - footer_color       string  Footer colour (default: same as color)
 *  - footer_align       string  'left' | 'center' | 'right' (default: 'center')
 *
 * Watermark (center overlay — use EC='H'):
 *  - watermark_text     string
 *  - watermark_image    string  File path or data URI
 *  - watermark_size     float   Fraction of QR size (default: 0.20)
 *  - watermark_bg       string  (default: #ffffff)
 *  - watermark_color    string  (default: #000000)
 *  - watermark_font     int     0 = auto
 *  - watermark_radius   int     Corner radius (default: 4)
 *  - watermark_padding  int     (default: 6)
 */
class QrSvgRenderer
{
    private const DEFAULTS = [
        'module_size'       => 8,
        'margin'            => 4,
        'color'             => '#000000',
        'bg'                => '#ffffff',
        'title'             => '',
        'title_size'        => 0,
        'title_color'       => '',
        'title_align'       => 'center',
        'footer'            => '',
        'footer_size'       => 0,
        'footer_color'      => '',
        'footer_align'      => 'center',
        'watermark_text'    => '',
        'watermark_image'   => '',
        'watermark_size'    => 0.20,
        'watermark_bg'      => '#ffffff',
        'watermark_color'   => '#000000',
        'watermark_font'    => 0,
        'watermark_radius'  => 4,
        'watermark_padding' => 6,
    ];

    /** @param bool[][] $matrix */
    public function render(array $matrix, array $options = []): string
    {
        $opt     = array_merge(self::DEFAULTS, $options);
        $modSize = max(1, (int) $opt['module_size']);
        $margin  = max(0, (int) $opt['margin']);
        $color   = htmlspecialchars((string) $opt['color'], ENT_XML1);
        $bg      = (string) $opt['bg'];

        $qrPx   = count($matrix) * $modSize;
        $qrFull = $qrPx + 2 * $margin * $modSize; // QR canvas including quiet zone

        $title      = trim((string) $opt['title']);
        $footer     = trim((string) $opt['footer']);
        $titleSize  = (int) $opt['title_size']  ?: 16;
        $footerSize = (int) $opt['footer_size'] ?: 13;
        $titleColor = htmlspecialchars($opt['title_color'] ?: $opt['color'], ENT_XML1);
        $footerColor= htmlspecialchars($opt['footer_color'] ?: $opt['color'], ENT_XML1);

        $titleH  = $title  !== '' ? $titleSize  + 10 : 0;
        $footerH = $footer !== '' ? $footerSize + 10 : 0;

        $svgW = $qrFull;
        $svgH = $titleH + $qrFull + $footerH;

        $qrOffsetY = $titleH; // QR block starts below title

        // Background
        $bgRect = '';
        if ($bg !== '') {
            $bgColor = htmlspecialchars($bg, ENT_XML1);
            $bgRect  = sprintf('<rect width="%d" height="%d" fill="%s"/>', $svgW, $svgH, $bgColor);
        }

        // Title
        $titleEl = '';
        if ($title !== '') {
            $tx     = $this->alignX($opt['title_align'], $svgW, $margin * $modSize);
            $anchor = $this->textAnchor($opt['title_align']);
            $titleEl = sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-size="%d" font-weight="bold" '
                . 'text-anchor="%s" dominant-baseline="hanging" fill="%s">%s</text>',
                $tx, 6, $titleSize, $anchor, $titleColor,
                htmlspecialchars($title, ENT_XML1)
            );
        }

        // QR modules
        $qrMarginPx = $margin * $modSize;
        $paths = '';
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (!$dark) continue;
                $x = $qrMarginPx + $c * $modSize;
                $y = $qrOffsetY + $qrMarginPx + $r * $modSize;
                $paths .= "M{$x},{$y}h{$modSize}v{$modSize}h-{$modSize}z";
            }
        }
        $pathEl = $paths !== ''
            ? sprintf('<path fill="%s" d="%s"/>', $color, $paths)
            : '';

        // Watermark
        $qrCX = (int) round($svgW / 2);
        $qrCY = $qrOffsetY + (int) round($qrFull / 2);
        $watermarkEl = $this->buildWatermark($opt, $svgW, $qrFull, $qrOffsetY, $qrCX, $qrCY);

        // Footer
        $footerEl = '';
        if ($footer !== '') {
            $fy     = $titleH + $qrFull + $footerSize + 4;
            $fx     = $this->alignX($opt['footer_align'], $svgW, $margin * $modSize);
            $anchor = $this->textAnchor($opt['footer_align']);
            $footerEl = sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-size="%d" '
                . 'text-anchor="%s" fill="%s">%s</text>',
                $fx, $fy, $footerSize, $anchor, $footerColor,
                htmlspecialchars($footer, ENT_XML1)
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'width="%d" height="%d" viewBox="0 0 %d %d">%s%s%s%s%s</svg>',
            $svgW, $svgH, $svgW, $svgH,
            $bgRect, $titleEl, $pathEl, $watermarkEl, $footerEl
        );
    }

    // -------------------------------------------------------------------------

    private function buildWatermark(array $opt, int $svgW, int $qrFull, int $qrOffsetY, int $cx, int $cy): string
    {
        $hasText  = trim((string) $opt['watermark_text'])  !== '';
        $hasImage = trim((string) $opt['watermark_image']) !== '';
        if (!$hasText && !$hasImage) return '';

        $fraction = min(0.30, max(0.05, (float) $opt['watermark_size']));
        $wmPx     = (int) round($qrFull * $fraction);
        $x        = $cx - intdiv($wmPx, 2);
        $y        = $cy - intdiv($wmPx, 2);
        $padding  = (int) $opt['watermark_padding'];
        $radius   = (int) $opt['watermark_radius'];
        $wmBg     = htmlspecialchars((string) $opt['watermark_bg'],    ENT_XML1);
        $wmColor  = htmlspecialchars((string) $opt['watermark_color'], ENT_XML1);

        $box = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d" fill="%s"/>',
            $x, $y, $wmPx, $wmPx, $radius, $radius, $wmBg
        );

        $inner = '';
        if ($hasImage) {
            $inner .= $this->buildImageEl(
                (string) $opt['watermark_image'],
                $x + $padding, $y + $padding,
                $wmPx - 2 * $padding, $wmPx - 2 * $padding
            );
        }
        if ($hasText) {
            $fontSize = (int) $opt['watermark_font'];
            if ($fontSize <= 0) {
                $available = $wmPx - 2 * $padding;
                $fontSize  = max(8, (int) floor($available / max(1, strlen((string)$opt['watermark_text'])) * 1.6));
                $fontSize  = min($fontSize, $available);
            }
            $textY  = $hasImage ? ($y + $wmPx - $padding - 2) : $cy;
            $anchor = $hasImage ? 'auto' : 'central';
            $inner .= sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-weight="bold" font-size="%d" '
                . 'text-anchor="middle" dominant-baseline="%s" fill="%s">%s</text>',
                $cx, $textY, $fontSize, $anchor, $wmColor,
                htmlspecialchars((string) $opt['watermark_text'], ENT_XML1)
            );
        }

        return '<g>' . $box . $inner . '</g>';
    }

    private function buildImageEl(string $src, int $x, int $y, int $w, int $h): string
    {
        if (str_starts_with($src, 'data:')) {
            $href = htmlspecialchars($src, ENT_XML1);
        } elseif (file_exists($src)) {
            $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg','jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml',
                'gif' => 'image/gif', 'webp' => 'image/webp', default => 'image/png',
            };
            $data = base64_encode((string) file_get_contents($src));
            $href = htmlspecialchars("data:{$mime};base64,{$data}", ENT_XML1);
        } else {
            return '';
        }
        return sprintf(
            '<image x="%d" y="%d" width="%d" height="%d" href="%s" preserveAspectRatio="xMidYMid meet"/>',
            $x, $y, $w, $h, $href
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
            'left'  => 'start', 'right' => 'end', default => 'middle',
        };
    }
}
