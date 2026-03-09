<?php

declare(strict_types=1);

namespace Laika\Barcode\Renderers;

/**
 * Renders a bool[][] QR matrix as an inline SVG string.
 *
 * Base options:
 *  - module_size        int     Pixels per module (default: 4)
 *  - margin             int     Quiet-zone modules (default: 4 — minimum per spec)
 *  - color              string  Dark module colour (default: #000000)
 *  - bg                 string  Background colour, '' = transparent (default: #ffffff)
 *
 * Watermark options (center overlay — use EC='H' for best scannability):
 *  - watermark_text     string  Text label in the center (e.g. 'LAIKA')
 *  - watermark_image    string  File path or base64 data URI of a PNG/JPEG/SVG logo
 *  - watermark_size     float   Fraction of total QR size (0.05–0.30, default: 0.25)
 *  - watermark_bg       string  Watermark box background colour (default: '#ffffff')
 *  - watermark_color    string  Watermark text colour (default: '#000000')
 *  - watermark_font     int     Font size in px, 0 = auto-fit (default: 0)
 *  - watermark_radius   int     Corner radius of the background box (default: 4)
 *  - watermark_padding  int     Padding inside the background box in px (default: 6)
 */
class QrSvgRenderer
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
        'watermark_radius'  => 4,
        'watermark_padding' => 6,
    ];

    /**
     * @param  bool[][]              $matrix  QR matrix (true = dark)
     * @param  array<string, mixed>  $options
     */
    public function render(array $matrix, array $options = []): string
    {
        $opt = array_merge(self::DEFAULTS, $options);

        $modSize = max(1, (int) $opt['module_size']);
        $margin  = max(0, (int) $opt['margin']);
        $color   = htmlspecialchars((string) $opt['color'], ENT_XML1);
        $bg      = (string) $opt['bg'];

        $qrSize = count($matrix);
        $fullPx = ($qrSize + 2 * $margin) * $modSize;

        // Background
        $bgRect = '';
        if ($bg !== '') {
            $bgColor = htmlspecialchars($bg, ENT_XML1);
            $bgRect  = sprintf('<rect width="%1$d" height="%1$d" fill="%2$s"/>', $fullPx, $bgColor);
        }

        // Data modules as a single compact <path>
        $paths = '';
        foreach ($matrix as $r => $row) {
            foreach ($row as $c => $dark) {
                if (!$dark) {
                    continue;
                }
                $x      = ($c + $margin) * $modSize;
                $y      = ($r + $margin) * $modSize;
                $paths .= "M{$x},{$y}h{$modSize}v{$modSize}h-{$modSize}z";
            }
        }

        $pathEl = $paths !== ''
            ? sprintf('<path fill="%s" d="%s"/>', $color, $paths)
            : '';

        // Watermark
        $watermarkEl = $this->buildWatermark($opt, $fullPx);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
            . 'width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d">%2$s%3$s%4$s</svg>',
            $fullPx,
            $bgRect,
            $pathEl,
            $watermarkEl
        );
    }

    // -------------------------------------------------------------------------
    // Watermark
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $opt */
    private function buildWatermark(array $opt, int $fullPx): string
    {
        $hasText  = trim((string) $opt['watermark_text']) !== '';
        $hasImage = trim((string) $opt['watermark_image']) !== '';

        if (!$hasText && !$hasImage) {
            return '';
        }

        $fraction = min(0.30, max(0.05, (float) $opt['watermark_size']));
        $wmPx     = (int) round($fullPx * $fraction);
        $cx       = (int) round($fullPx / 2);
        $cy       = (int) round($fullPx / 2);
        $x        = $cx - intdiv($wmPx, 2);
        $y        = $cy - intdiv($wmPx, 2);
        $padding  = (int) $opt['watermark_padding'];
        $radius   = (int) $opt['watermark_radius'];
        $wmBg     = htmlspecialchars((string) $opt['watermark_bg'], ENT_XML1);
        $wmColor  = htmlspecialchars((string) $opt['watermark_color'], ENT_XML1);

        // Rounded background box
        $box = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" ry="%d" fill="%s"/>',
            $x, $y, $wmPx, $wmPx, $radius, $radius, $wmBg
        );

        $inner = '';

        if ($hasImage) {
            $inner .= $this->buildImageEl(
                (string) $opt['watermark_image'],
                $x + $padding,
                $y + $padding,
                $wmPx - 2 * $padding,
                $wmPx - 2 * $padding
            );
        }

        if ($hasText) {
            $fontSize = (int) $opt['watermark_font'];
            if ($fontSize <= 0) {
                $text      = (string) $opt['watermark_text'];
                $available = $wmPx - 2 * $padding;
                $fontSize  = max(8, (int) floor($available / max(1, strlen($text)) * 1.6));
                $fontSize  = min($fontSize, $available);
            }

            // If image is also present push text to bottom; otherwise centre it
            $textY  = $hasImage ? ($y + $wmPx - $padding - 2) : $cy;
            $anchor = $hasImage ? 'auto'                       : 'central';

            $escaped = htmlspecialchars((string) $opt['watermark_text'], ENT_XML1);
            $inner  .= sprintf(
                '<text x="%d" y="%d" font-family="sans-serif" font-weight="bold" font-size="%d" '
                . 'text-anchor="middle" dominant-baseline="%s" fill="%s">%s</text>',
                $cx, $textY, $fontSize, $anchor, $wmColor, $escaped
            );
        }

        return '<g>' . $box . $inner . '</g>';
    }

    /** Build an SVG <image> element from a file path or data URI. */
    private function buildImageEl(string $src, int $x, int $y, int $w, int $h): string
    {
        if (str_starts_with($src, 'data:')) {
            $href = htmlspecialchars($src, ENT_XML1);
        } elseif (file_exists($src)) {
            $ext  = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'svg'         => 'image/svg+xml',
                'gif'         => 'image/gif',
                'webp'        => 'image/webp',
                default       => 'image/png',
            };
            $data = base64_encode((string) file_get_contents($src));
            $href = htmlspecialchars("data:{$mime};base64,{$data}", ENT_XML1);
        } else {
            return ''; // file not found — skip silently
        }

        return sprintf(
            '<image x="%d" y="%d" width="%d" height="%d" href="%s" preserveAspectRatio="xMidYMid meet"/>',
            $x, $y, $w, $h, $href
        );
    }
}
