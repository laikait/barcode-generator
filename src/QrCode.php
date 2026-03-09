<?php

declare(strict_types=1);

namespace Laika\Barcode;

use Laika\Barcode\Encoders\QrEncoder;
use Laika\Barcode\Exceptions\InvalidDataException;
use Laika\Barcode\Renderers\QrSvgRenderer;

/**
 * Fluent QR Code generator.
 *
 * Usage:
 *   $svg = QrCode::data('https://example.com')->svg();
 *
 *   $svg = QrCode::data('Hello')
 *       ->ec('H')
 *       ->watermarkText('LAIKA')
 *       ->svg();
 *
 *   $svg = QrCode::data('Hello')
 *       ->ec('H')
 *       ->watermarkImage('/path/to/logo.png')
 *       ->svg();
 *
 *   $matrix = QrCode::data('test')->matrix(); // bool[][]
 */
class QrCode
{
    private string $data    = '';
    private string $ecLevel = 'M';
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
     * @param  string  $level  'L' (7%), 'M' (15%), 'Q' (25%), 'H' (30%)
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
     * Automatically forces EC='H' unless you override it afterwards.
     *
     * @param  string  $text     The text to display (e.g. 'LAIKA', '★')
     * @param  array   $options  Any watermark_* option overrides
     */
    public function watermarkText(string $text, array $options = []): self
    {
        return $this->ec('H')->options(array_merge([
            'watermark_text'    => $text,
            'watermark_size'    => 0.25,
            'watermark_bg'      => '#ffffff',
            'watermark_color'   => '#000000',
            'watermark_radius'  => 4,
            'watermark_padding' => 6,
        ], $options));
    }

    /**
     * Add an image/logo watermark to the center of the QR code.
     * Automatically forces EC='H' unless you override it afterwards.
     *
     * @param  string  $src      File path or base64 data URI (data:image/png;base64,...)
     * @param  array   $options  Any watermark_* option overrides
     */
    public function watermarkImage(string $src, array $options = []): self
    {
        return $this->ec('H')->options(array_merge([
            'watermark_image'   => $src,
            'watermark_size'    => 0.25,
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
     * Return the raw bool[][] matrix (true = dark module).
     *
     * @return bool[][]
     * @throws InvalidDataException
     */
    public function matrix(): array
    {
        return (new QrEncoder())->encode($this->data, $this->ecLevel);
    }

    /** Supported EC levels. */
    public static function ecLevels(): array
    {
        return ['L', 'M', 'Q', 'H'];
    }
}
