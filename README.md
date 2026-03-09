# Laika Barcode Generator

A lightweight PHP 8.1+ barcode generator library supporting 1D and 2D barcode formats with **SVG and PNG** output, watermarks, and file saving — zero runtime dependencies.

---

## Supported Formats

### 1D Barcodes — `Laika\Barcode\Barcode`

| Key       | Format   | Input                              |
|-----------|----------|------------------------------------|
| `code128` | Code 128 | Any printable ASCII                |
| `code39`  | Code 39  | A–Z, 0–9, `-` `.` ` ` `$ / + %`  |
| `ean13`   | EAN-13   | 12 or 13 digits                    |
| `ean8`    | EAN-8    | 7 or 8 digits                      |
| `upca`    | UPC-A    | 11 or 12 digits                    |

### 2D Barcodes — `Laika\Barcode\QrCode`

| Format  | Versions | EC Levels  | Input           |
|---------|----------|------------|-----------------|
| QR Code | 1–10     | L, M, Q, H | Any bytes/UTF-8 |

---

## Requirements

- PHP **8.1+**
- GD extension *(only for PNG output)*

---

## Installation

```bash
composer require laika/barcode
```

---

## 1D Barcodes

### SVG

```php
use Laika\Barcode\Barcode;

$svg = Barcode::type('code128')->data('Hello, World!')->svg();

header('Content-Type: image/svg+xml');
echo $svg;
```

### PNG *(requires GD)*

```php
$png = Barcode::type('ean13')->data('590123412345')->png();

header('Content-Type: image/png');
echo $png;
```

### Save to File

Format is inferred from the file extension (`.svg` or `.png`). Directories are created automatically.

```php
// Returns the resolved absolute path
$path = Barcode::type('code128')->data('ABC-123')->save('/var/www/barcodes/label.svg');
$path = Barcode::type('ean13')->data('590123412345')->save('/var/www/barcodes/product.png');
```

### Inline in HTML

```php
$svg = Barcode::type('ean8')->data('9638507')->svg();
echo '<div>' . $svg . '</div>';
```

### Options

All options can be passed to `->options([...])` (builder-level) or directly to `->svg([...])` / `->png([...])` / `->save($path, [...])`.

**SVG options:**

| Option              | Type     | Default     | Description                              |
|---------------------|----------|-------------|------------------------------------------|
| `height`            | `int`    | `80`        | Bar height in pixels                     |
| `module_width`      | `int`    | `2`         | Width of one narrow module (px)          |
| `margin`            | `int`    | `10`        | Side/top/bottom margin (px)              |
| `color`             | `string` | `#000000`   | Bar and label colour (hex)               |
| `bg`                | `string` | `#ffffff`   | Background colour, `''` = transparent    |
| `show_label`        | `bool`   | `true`      | Print data label below bars              |
| `font_size`         | `int`    | `12`        | Label font size in px                    |
| `watermark_text`    | `string` | `''`        | Diagonal overlay text (e.g. `'SAMPLE'`)  |
| `watermark_color`   | `string` | `#000000`   | Watermark text colour                    |
| `watermark_opacity` | `float`  | `0.15`      | Opacity 0.0–1.0                          |
| `watermark_rotate`  | `int`    | `-20`       | Rotation in degrees                      |
| `watermark_position`| `string` | `'center'`  | `'top'` \| `'center'` \| `'bottom'`     |
| `watermark_font`    | `int`    | `0`         | Font size in px, `0` = auto              |

**PNG options** *(same as SVG, with these differences):*

| Option      | Type              | Default           | Description                                         |
|-------------|-------------------|-------------------|-----------------------------------------------------|
| `color`     | `string\|int[]`   | `[0, 0, 0]`       | Hex string or `[r, g, b]` array                     |
| `bg`        | `string\|int[]`   | `[255, 255, 255]` | Hex string, `[r, g, b]` array, or `null` = transparent |
| `font_size` | `int`             | `3`               | GD built-in font (1–5)                              |

### 1D Watermark

The watermark is a semi-transparent diagonal text drawn over the bars — decorative only. Keep `watermark_opacity` ≤ `0.20` to avoid interfering with scanning.

```php
$svg = Barcode::type('code128')
    ->data('HF5H65AP')
    ->options([
        'watermark_text'     => 'SAMPLE',
        'watermark_color'    => '#cc0000',
        'watermark_opacity'  => 0.15,
        'watermark_rotate'   => -25,
        'watermark_position' => 'center',
    ])
    ->svg();
```

---

## QR Code

### EC Levels

| Level | Recovery | Default | Best For                         |
|-------|----------|---------|----------------------------------|
| `L`   | ~7%      |         | Clean print environments         |
| `M`   | ~15%     |         | General use                      |
| `Q`   | ~25%     |         | Printed labels                   |
| `H`   | ~30%     | ✓       | Watermarks / damaged environments |

> **`H` is the default.** It provides the most error recovery and is required when using a center watermark.

### SVG

```php
use Laika\Barcode\QrCode;

$svg = QrCode::data('https://example.com')->svg();

header('Content-Type: image/svg+xml');
echo $svg;
```

### PNG *(requires GD)*

```php
$png = QrCode::data('https://example.com')->png();

header('Content-Type: image/png');
echo $png;
```

### Save to File

```php
// SVG
$path = QrCode::data('https://example.com')->save('/var/www/qr/code.svg');

// PNG
$path = QrCode::data('https://example.com')->save('/var/www/qr/code.png');
```

### Options

| Option        | Type     | Default     | Description                                     |
|---------------|----------|-------------|-------------------------------------------------|
| `module_size` | `int`    | `8`         | Pixels per module                               |
| `margin`      | `int`    | `4`         | Quiet-zone modules (minimum 4 per QR spec)      |
| `color`       | `string` | `#000000`   | Dark module colour (hex)                        |
| `bg`          | `string` | `#ffffff`   | Background colour, `''` = transparent           |

```php
$svg = QrCode::data('Hello, World!')
    ->ec('Q')
    ->options([
        'module_size' => 10,
        'color'       => '#1a1a2e',
        'bg'          => '#f0f4ff',
    ])
    ->svg();
```

### Center Watermark

QR codes support a center watermark (text or image/logo) because error-correction redundancy compensates for the obscured modules. Always use **EC=H** (enforced automatically by the watermark helpers).

> Keep `watermark_size` ≤ `0.22` to stay safely within the 30% recovery budget and avoid clipping the finder patterns.

**Text watermark:**

```php
// SVG
$svg = QrCode::data('HG6GH5H33')
    ->watermarkText('LAIKA')
    ->svg();

// PNG
$png = QrCode::data('HG6GH5H33')
    ->watermarkText('LAIKA')
    ->png();

// Styled
$svg = QrCode::data('HG6GH5H33')
    ->watermarkText('★', [
        'watermark_size'   => 0.20,
        'watermark_bg'     => '#1a1a2e',
        'watermark_color'  => '#ffffff',
        'watermark_radius' => 50,
        'watermark_font'   => 28,
    ])
    ->svg();
```

**Image/logo watermark:**

```php
// From file path
$svg = QrCode::data('HG6GH5H33')
    ->watermarkImage('/path/to/logo.png')
    ->svg();

// From base64 data URI
$svg = QrCode::data('HG6GH5H33')
    ->watermarkImage('data:image/png;base64,...')
    ->svg();

// PNG output with logo
$png = QrCode::data('HG6GH5H33')
    ->watermarkImage('/path/to/logo.png')
    ->png();
```

**Watermark options:**

| Option              | Type     | Default     | Description                                        |
|---------------------|----------|-------------|----------------------------------------------------|
| `watermark_text`    | `string` | `''`        | Text to display in the center box                  |
| `watermark_image`   | `string` | `''`        | File path or `data:image/...;base64,...` URI        |
| `watermark_size`    | `float`  | `0.20`      | Fraction of QR canvas size (max `0.22` recommended)|
| `watermark_bg`      | `string` | `#ffffff`   | Background box colour                              |
| `watermark_color`   | `string` | `#000000`   | Text colour                                        |
| `watermark_font`    | `int`    | `0`         | Font size in px, `0` = auto-fit                    |
| `watermark_radius`  | `int`    | `4`         | Corner radius of background box (SVG only)         |
| `watermark_padding` | `int`    | `6`         | Padding inside background box in px                |

### Raw Matrix

```php
// Returns bool[][] — true = dark module, false = light
$matrix = QrCode::data('test')->matrix();

foreach ($matrix as $row => $cols) {
    foreach ($cols as $col => $dark) {
        // drive your own renderer
    }
}
```

---

## Extending

### Register a Custom 1D Encoder

```php
use Laika\Barcode\Barcode;
use Laika\Barcode\Contracts\BarcodeInterface;

class MyEncoder implements BarcodeInterface
{
    public function encode(string $data): array { /* return bar array */ }
    public function validate(string $data): bool { /* ... */ }
    public function label(string $data): string { return $data; }
}

Barcode::register('myformat', MyEncoder::class);

$svg = Barcode::type('myformat')->data('test')->svg();
```

### Custom 1D Renderer

```php
use Laika\Barcode\Contracts\RendererInterface;

class HtmlRenderer implements RendererInterface
{
    public function render(array $bars, string $label, array $options = []): string
    {
        $html = '<div style="display:flex;">';
        foreach ($bars as $bar) {
            $color = $bar['bar'] ? '#000' : 'transparent';
            $html .= sprintf(
                '<div style="width:%dpx;height:80px;background:%s;"></div>',
                $bar['width'] * 2,
                $color
            );
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