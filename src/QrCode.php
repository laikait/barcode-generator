<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode;

use Laika\Barcode\Exceptions\InvalidDataException;
use Laika\Barcode\Exceptions\BarcodeException;
use Laika\Barcode\Renderers\QrPngRenderer;
use Laika\Barcode\Renderers\QrSvgRenderer;
use Laika\Barcode\Encoders\QrEncoder;

/**
 * Fluent QR Code generator.
 *
 * Usage:
 *   $svg = QrCode::data('https://example.com')->svg();
 *   $png = QrCode::data('https://example.com')->png();
 *
 *   $svg = QrCode::data('Hello')
 *       ->ec('H')
 *       ->watermarkText('LAIKA')
 *       ->svg();
 *
 *   QrCode::data('Hello')->save('/path/to/qr.png');
 *   QrCode::data('Hello')->save('/path/to/qr.svg');
 *
 *   $matrix = QrCode::data('test')->matrix(); // bool[][]
 */
class QrCode
{
    private string $data    = '';
    private string $ecLevel = 'H';
    private array  $options = [];

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function data(string $data): self
    {
        $i       = new self();
        $i->data = $data;

        return $i;
    }

    // -------------------------------------------------------------------------
    // Builder — base
    // -------------------------------------------------------------------------

    /**
     * Set the error-correction level.
     *
     * @param  string  $level  'L' (7%), 'M' (15%), 'Q' (25%), 'H' (30% — default)
     */
    public function ec(string $level): self
    {
        $c          = clone $this;
        $c->ecLevel = strtoupper(trim($level));

        return $c;
    }

    /** Merge arbitrary render options. */
    public function options(array $options): self
    {
        $c          = clone $this;
        $c->options = array_merge($this->options, $options);

        return $c;
    }

    // -------------------------------------------------------------------------
    // Builder — watermark helpers
    // -------------------------------------------------------------------------

    /**
     * Add a text watermark to the center of the QR code.
     * Automatically forces EC='H'.
     */
    public function watermarkText(string $text, array $options = []): self
    {
        return $this->ec('H')->options(array_merge([
            'watermark_text'    => $text,
            'watermark_size'    => 0.20,
            'watermark_bg'      => '#ffffff',
            'watermark_color'   => '#000000',
            'watermark_radius'  => 4,
            'watermark_padding' => 6,
        ], $options));
    }

    /**
     * Add an image/logo watermark to the center of the QR code.
     * Automatically forces EC='H'.
     *
     * @param  string  $src  File path or base64 data URI (data:image/png;base64,...)
     */
    public function watermarkImage(string $src, array $options = []): self
    {
        return $this->ec('H')->options(array_merge([
            'watermark_image'   => $src,
            'watermark_size'    => 0.20,
            'watermark_bg'      => '#ffffff',
            'watermark_radius'  => 4,
            'watermark_padding' => 4,
        ], $options));
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Render the QR code as an inline SVG string.
     *
     * @param  array  $options  Merged on top of instance options.
     */
    public function svg(array $options = []): string
    {
        return (new QrSvgRenderer())->render(
            $this->matrix(),
            array_merge($this->options, $options)
        );
    }

    /**
     * Render the QR code as raw PNG bytes (requires GD extension).
     * @param array $options  Merged on top of instance options.
     * @return string
     */
    public function png(array $options = []): string
    {
        return (new QrPngRenderer())->render(
            $this->matrix(),
            array_merge($this->options, $options)
        );
    }

    /**
     * Render as a PNG base64 data URI.
     * @param array $options  Merged on top of instance options.
     * @return string e.g. data:image/svg+xml;base64,...
     */
    public function pngBase64(array $options = []): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($options));
    }

    /**
     * Render as a SVG base64 data URI.
     * @param array $options  Merged on top of instance options.
     * @return string e.g. data:image/svg+xml;base64,...
     */
    public function svgBase64(array $options = []): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($options));
    }

    /**
     * Return the raw bool[][] matrix (true = dark module).
     *
     * @return bool[][]
     * @throws InvalidDataException
     */
    public function matrix(): array
    {
        return (new QrEncoder())->encode($this->data, $this->ecLevel);
    }

    // -------------------------------------------------------------------------
    // Save to file
    // -------------------------------------------------------------------------

    /**
     * Render and save the QR code to a file.
     *
     * Format is inferred from the file extension:
     *   .svg  → SVG string
     *   .png  → PNG binary (requires GD)
     *
     * @param  string  $path     Destination file path (e.g. '/var/www/qr/code.png')
     * @param  array   $options  Merged on top of instance options.
     * @return string            Resolved absolute path.
     *
     * @throws BarcodeException
     */
    public function save(string $path, array $options = []): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $content = match ($ext) {
            'svg'   => $this->svg($options),
            'png'   => $this->png($options),
            default => throw new BarcodeException(
                "Unsupported file extension \".{$ext}\". Use .svg or .png."
            ),
        };

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new BarcodeException("Could not create directory: {$dir}");
        }

        if (file_put_contents($path, $content) === false) {
            throw new BarcodeException("Could not write QR code file: {$path}");
        }

        return realpath($path) ?: $path;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Supported EC levels. */
    public static function ecLevels(): array
    {
        return ['L', 'M', 'Q', 'H'];
    }
}