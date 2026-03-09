# Laika Barcode

A lightweight, zero-dependency PHP 8.1+ barcode generator library supporting 1D and 2D barcode formats with SVG and PNG output.

## Supported Types

### 1D Barcodes (`Laika\Barcode\Barcode`)

| Key       | Format   | Input                            |
|-----------|----------|----------------------------------|
| `code128` | Code 128 | Any printable ASCII              |
| `code39`  | Code 39  | A–Z, 0–9, `-`, `.`, ` `, `$/+%` |
| `ean13`   | EAN-13   | 12 or 13 digits                  |
| `ean8`    | EAN-8    | 7 or 8 digits                    |
| `upca`    | UPC-A    | 11 or 12 digits                  |

### 2D Barcodes (`Laika\Barcode\QrCode`)

| Format  | Versions | EC Levels       | Input          |
|---------|----------|-----------------|----------------|
| QR Code | 1–10     | L, M, Q, H      | Any bytes/UTF-8 |

---

## Requirements

- PHP **8.1+**
- GD extension (only for PNG output)

---

## Installation

```bash
composer require laika/barcode
```

---

## Quick Start

```php
use Laika\Barcode\Barcode;

// Generate a Code 128 SVG
$svg = Barcode::type('code128')
    ->data('Hello, World!')
    ->svg();

// Serve it directly
header('Content-Type: image/svg+xml');
echo $svg;
```

---

## Usage

### SVG Output

```php
$svg = Barcode::type('ean13')
    ->data('590123412345')   // 12 digits — check digit auto-computed
    ->svg();
```

### PNG Output *(requires GD)*

```php
$png = Barcode::type('code128')
    ->data('LAIKA-2025')
    ->png();

header('Content-Type: image/png');
echo $png;
```

### Save to File

```php
file_put_contents('barcode.svg', Barcode::type('code39')->data('ABC-123')->svg());
file_put_contents('barcode.png', Barcode::type('upca')->data('01234567890')->png());
```

### Inline in HTML

```php
$svg = Barcode::type('ean8')->data('9638507')->svg();
echo '<div>' . $svg . '</div>';
```

---

## Options

All options can be passed to `->options([...])` (applied globally) or directly to `->svg([...])` / `->png([...])`.

### SVG Options

| Option         | Type     | Default     | Description                         |
|----------------|----------|-------------|-------------------------------------|
| `height`       | `int`    | `80`        | Bar height in pixels                |
| `module_width` | `int`    | `2`         | Width of one narrow module (px)     |
| `margin`       | `int`    | `10`        | Left/right/top/bottom margin (px)   |
| `color`        | `string` | `#000000`   | Bar and text colour (hex)           |
| `bg`           | `string` | `#ffffff`   | Background colour, `''` = transparent |
| `show_label`   | `bool`   | `true`      | Display text label below bars       |
| `font_size`    | `int`    | `12`        | Label font size in px               |

### PNG Options

Same as SVG, except:

| Option      | Type             | Default           | Description                          |
|-------------|------------------|-------------------|--------------------------------------|
| `color`     | `string\|int[]`  | `[0, 0, 0]`       | Hex string or `[r, g, b]` array      |
| `bg`        | `string\|int[]`  | `[255, 255, 255]` | Hex string, `[r, g, b]`, or `null` (transparent) |
| `font_size` | `int`            | `3`               | GD built-in font size (1–5)          |

### Example with Options

```php
$svg = Barcode::type('code128')
    ->data('LAIKA')
    ->options([
        'height'       => 60,
        'module_width' => 3,
        'color'        => '#003366',
        'bg'           => '#f0f4ff',
        'show_label'   => true,
        'font_size'    => 14,
    ])
    ->svg();
```

---

## QR Code

QR codes are generated via the `QrCode` facade and support all four error-correction levels.

### EC Levels

| Level | Recovery Capacity | Best For                     |
|-------|-------------------|------------------------------|
| `L`   | ~7%               | Clean environments           |
| `M`   | ~15%              | General use (default)        |
| `Q`   | ~25%              | Printed labels               |
| `H`   | ~30%              | High-damage environments     |

### QR SVG Output

```php
use Laika\Barcode\QrCode;

$svg = QrCode::data('https://example.com')->svg();

// With EC level and custom options
$svg = QrCode::data('Hello, World!')
    ->ec('H')
    ->options([
        'module_size' => 6,
        'margin'      => 4,
        'color'       => '#1a1a2e',
        'bg'          => '#ffffff',
    ])
    ->svg();

header('Content-Type: image/svg+xml');
echo $svg;
```

### QR Options

| Option        | Type     | Default     | Description                                |
|---------------|----------|-------------|--------------------------------------------|
| `module_size` | `int`    | `4`         | Pixels per module                          |
| `margin`      | `int`    | `4`         | Quiet-zone size in modules (min 4 per spec)|
| `color`       | `string` | `#000000`   | Dark module colour (hex)                   |
| `bg`          | `string` | `#ffffff`   | Background colour, `''` = transparent      |

### Raw Matrix (Custom Rendering)

```php
// Returns bool[][] — true = dark module
$matrix = QrCode::data('test')->ec('Q')->matrix();

foreach ($matrix as $row => $cols) {
    foreach ($cols as $col => $dark) {
        // render $dark however you like
    }
}
```

---

## Extending

### Register a Custom Encoder

```php
use Laika\Barcode\Barcode;
use Laika\Barcode\Contracts\BarcodeInterface;

class MyEncoder implements BarcodeInterface
{
    public function encode(string $data): array { /* ... */ }
    public function validate(string $data): bool { /* ... */ }
    public function label(string $data): string { return $data; }
}

Barcode::register('myformat', MyEncoder::class);

$svg = Barcode::type('myformat')->data('test')->svg();
```

### Custom Renderer

```php
use Laika\Barcode\Barcode;
use Laika\Barcode\Contracts\RendererInterface;

class HtmlRenderer implements RendererInterface
{
    public function render(array $bars, string $label, array $options = []): string
    {
        $html = '<div style="display:flex;">';
        foreach ($bars as $bar) {
            $color = $bar['bar'] ? '#000' : 'transparent';
            $html .= sprintf('<div style="width:%dpx;height:80px;background:%s;"></div>', $bar['width'] * 2, $color);
        }
        return $html . '</div><p>' . htmlspecialchars($label) . '</p>';
    }
}

$html = Barcode::type('code39')
    ->data('HELLO')
    ->renderWith(new HtmlRenderer());
```

---

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```

---

## License

MIT © Laika
