<?php

declare(strict_types=1);

namespace Laika\Barcode;

use Laika\Barcode\Contracts\BarcodeInterface;
use Laika\Barcode\Contracts\RendererInterface;
use Laika\Barcode\Encoders\Code128Encoder;
use Laika\Barcode\Encoders\Code39Encoder;
use Laika\Barcode\Encoders\Ean13Encoder;
use Laika\Barcode\Encoders\Ean8Encoder;
use Laika\Barcode\Encoders\UpcaEncoder;
use Laika\Barcode\Exceptions\BarcodeException;
use Laika\Barcode\Renderers\PngRenderer;
use Laika\Barcode\Renderers\SvgRenderer;

/**
 * Main entry point for the Laika Barcode library.
 *
 * Usage:
 *   // SVG (default)
 *   $svg = Barcode::type('code128')->data('Hello World')->svg();
 *
 *   // PNG bytes
 *   $png = Barcode::type('ean13')->data('590123412345')->png();
 *
 *   // Custom options
 *   $svg = Barcode::type('code39')
 *       ->data('ABC-123')
 *       ->options(['height' => 60, 'color' => '#003366'])
 *       ->svg();
 */
class Barcode
{
    /** @var array<string, class-string<BarcodeInterface>> */
    private static array $encoders = [
        'code128' => Code128Encoder::class,
        'code39'  => Code39Encoder::class,
        'ean13'   => Ean13Encoder::class,
        'ean8'    => Ean8Encoder::class,
        'upca'    => UpcaEncoder::class,
    ];

    private BarcodeInterface $encoder;
    private string $data       = '';
    private array  $options    = [];

    private function __construct(BarcodeInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Create a Barcode builder for the given type.
     *
     * @param  string  $type  One of: code128, code39, ean13, ean8, upca
     *
     * @throws BarcodeException
     */
    public static function type(string $type): self
    {
        $key = strtolower($type);

        if (!isset(self::$encoders[$key])) {
            throw new BarcodeException(sprintf(
                'Unknown barcode type "%s". Supported types: %s',
                $type,
                implode(', ', array_keys(self::$encoders))
            ));
        }

        $encoderClass = self::$encoders[$key];

        return new self(new $encoderClass());
    }

    /**
     * Register a custom encoder.
     *
     * @param  class-string<BarcodeInterface>  $encoderClass
     */
    public static function register(string $type, string $encoderClass): void
    {
        self::$encoders[strtolower($type)] = $encoderClass;
    }

    // -------------------------------------------------------------------------
    // Builder
    // -------------------------------------------------------------------------

    public function data(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    /** @param array<string, mixed> $options */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    // -------------------------------------------------------------------------
    // Renderers
    // -------------------------------------------------------------------------

    /** Render as SVG string. */
    public function svg(array $options = []): string
    {
        return $this->renderWith(new SvgRenderer(), $options);
    }

    /** Render as raw PNG bytes. */
    public function png(array $options = []): string
    {
        return $this->renderWith(new PngRenderer(), $options);
    }

    /** Use a custom renderer. */
    public function renderWith(RendererInterface $renderer, array $options = []): string
    {
        $bars  = $this->encoder->encode($this->data);
        $label = $this->encoder->label($this->data);
        $opts  = array_merge($this->options, $options);

        return $renderer->render($bars, $label, $opts);
    }

    // -------------------------------------------------------------------------
    // Save to file
    // -------------------------------------------------------------------------

    /**
     * Render and save the barcode to a file.
     *
     * The format is inferred from the file extension:
     *   .svg  → SVG string
     *   .png  → PNG binary (requires GD)
     *
     * @param  string  $path     Destination file path (e.g. '/var/www/barcodes/code128.svg')
     * @param  array   $options  Render options merged on top of instance options.
     * @return string            The resolved absolute path.
     *
     * @throws BarcodeException  If the extension is unsupported or the directory is not writable.
     */
    public function save(string $path, array $options = []): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $content = match ($ext) {
            'svg'  => $this->svg($options),
            'png'  => $this->png($options),
            default => throw new BarcodeException(
                "Unsupported file extension \".{$ext}\". Use .svg or .png."
            ),
        };

        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new BarcodeException("Could not create directory: {$dir}");
            }
        }

        if (file_put_contents($path, $content) === false) {
            throw new BarcodeException("Could not write barcode file: {$path}");
        }

        return realpath($path) ?: $path;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Check whether the current data is valid for the chosen encoder. */
    public function isValid(): bool
    {
        return $this->encoder->validate($this->data);
    }

    /** Return the list of registered barcode types. */
    public static function supportedTypes(): array
    {
        return array_keys(self::$encoders);
    }
}
