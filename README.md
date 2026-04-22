# Laika Barcode Generator

A lightweight PHP 8.1+ barcode generator library supporting 1D and 2D barcode formats with **SVG and PNG** output, watermarks, title/footer text, and file saving — zero runtime dependencies.

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
composer require laikait/barcode-generator
```

---

## 1D Barcodes

### Quick Start

```php
use Laika\Barcode\Barcode;

$svg = Barcode::type('code128')->data('Hello, World!')->svg();

header('Content-Type: image/svg+xml');
echo $svg;
```

### SVG

```php
$svg = Barcode::type('ean13')
    ->data('590123412345')   // 12 digits — check digit auto-computed
    ->svg();
```

### PNG *(requires GD)*

```php
$png = Barcode::type('code128')->data('LAIKA-2025')->png();

header('Content-Type: image/png');
echo $png;
```

### Save to File

Format is inferred from the file extension (`.svg` or `.png`). Directories are created automatically.

```php
$path = Barcode::type('code128')->data('ABC-123')->save('/var/www/barcodes/label.svg');
$path = Barcode::type('ean13')->data('590123412345')->save('/var/www/barcodes/product.png');
```

### Inline in HTML

```php
$svg = Barcode::type('ean8')->data('9638507')->svg();
echo '<div>' . $svg . '</div>';
```

### Use in HTML TAG

```php
$svg = Barcode::type('ean8')->data('9638507')->svgBase64();
$png = Barcode::type('ean8')->data('9638507')->pngBase64();
echo '<img src="<?= $svg ?>" alt="Base64 SVG Barcode">';
echo '<img src="<?= $png ?>" alt="Base64 PNG Barcode">';

### Title & Footer

Add a title above and/or footer text below the barcode.

```php
$svg = Barcode::type('code128')
    ->data('HF5H65AP')
    ->options([
        'title'        => 'Product Label',
        'title_color'  => '#003366',
        'title_align'  => 'center',
        'footer'       => 'Scan at checkout',
        'footer_color' => '#666666',
        'footer_align' => 'right',
    ])
    ->svg();
```

### Watermark

A semi-transparent diagonal text drawn over the bars — decorative only. Keep `watermark_opacity` ≤ `0.20` to avoid affecting scannability.

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

### All Options

All options can be passed to `->options([...])` (builder-level) or directly to `->svg([...])` / `->png([...])` / `->save($path, [...])`.

**Base:**

| Option         | Type     | Default     | Description                              |
|----------------|----------|-------------|------------------------------------------|
| `height`       | `int`    | `80`        | Bar height in pixels                     |
| `module_width` | `int`    | `2`         | Width of one narrow module (px)          |
| `margin`       | `int`    | `10`        | Side/top/bottom margin (px)              |
| `color`        | `string` | `#000000`   | Bar and label colour (hex)               |
| `bg`           | `string` | `#ffffff`   | Background colour, `''` = transparent    |
| `show_label`   | `bool`   | `true`      | Print data label below bars              |
| `font_size`    | `int`    | `12`        | Data label font size in px (SVG) / GD font 1–5 (PNG) |

**Title & footer:**

| Option         | Type     | Default          | Description                           |
|----------------|----------|------------------|---------------------------------------|
| `title`        | `string` | `''`             | Text printed above the barcode        |
| `title_size`   | `int`    | `0` (auto)       | Font size in px                       |
| `title_color`  | `string` | same as `color`  | Title text colour                     |
| `title_align`  | `string` | `'center'`       | `'left'` \| `'center'` \| `'right'`  |
| `footer`       | `string` | `''`             | Text printed below the barcode        |
| `footer_size`  | `int`    | `0` (auto)       | Font size in px                       |
| `footer_color` | `string` | same as `color`  | Footer text colour                    |
| `footer_align` | `string` | `'center'`       | `'left'` \| `'center'` \| `'right'`  |

**Watermark:**

| Option               | Type     | Default     | Description                              |
|----------------------|----------|-------------|------------------------------------------|
| `watermark_text`     | `string` | `''`        | Diagonal overlay text (e.g. `'SAMPLE'`)  |
| `watermark_color`    | `string` | `#000000`   | Watermark text colour                    |
| `watermark_opacity`  | `float`  | `0.15`      | Opacity 0.0–1.0                          |
| `watermark_rotate`   | `int`    | `-20`       | Rotation in degrees                      |
| `watermark_position` | `string` | `'center'`  | `'top'` \| `'center'` \| `'bottom'`     |
| `watermark_font`     | `int`    | `0`         | Font size in px, `0` = auto              |

> **PNG note:** `color` and `bg` also accept `[r, g, b]` arrays. `bg` can be `null` for transparency. `font_size` uses GD built-in fonts (1–5).

---

## QR Code

### EC Levels

| Level | Recovery | Default | Best For                          |
|-------|----------|---------|-----------------------------------|
| `L`   | ~7%      |         | Clean print environments          |
| `M`   | ~15%     |         | General use                       |
| `Q`   | ~25%     |         | Printed labels                    |
| `H`   | ~30%     | ✓       | Watermarks / damaged environments |

> **`H` is the default.** Required when using a center watermark.

### SVG

```php
use Laika\Barcode\QrCode;

$svg = QrCode::data('https://example.com')->svg();
```

### PNG *(requires GD)*

```php
$png = QrCode::data('https://example.com')->png();

header('Content-Type: image/png');
echo $png;
```

### Save to File

```php
$path = QrCode::data('https://example.com')->save('/var/www/qr/code.svg');
$path = QrCode::data('https://example.com')->save('/var/www/qr/code.png');
```

### Use in HTML TAG
```php
$svg = QrCode::type('ean8')->data('9638507')->svgBase64();
$png = QrCode::type('ean8')->data('9638507')->pngBase64();
echo '<img src="<?= $svg ?>" alt="Base64 SVG QrCode">';
echo '<img src="<?= $png ?>" alt="Base64 PNG QrCode">';
```

### Title & Footer

```php
// SVG
$svg = QrCode::data('https://laika.dev')
    ->options([
        'title'        => 'Scan to visit',
        'title_color'  => '#003366',
        'footer'       => 'laika.dev — PHP Framework',
        'footer_color' => '#999999',
        'footer_align' => 'center',
    ])
    ->svg();

// PNG
$png = QrCode::data('https://laika.dev')
    ->options([
        'title'  => 'Scan to visit',
        'footer' => 'laika.dev',
    ])
    ->png();
```

### Center Watermark

QR codes support a center watermark (text or image/logo). Error-correction redundancy compensates for the obscured modules. Always uses **EC=H** (enforced automatically).

> Keep `watermark_size` ≤ `0.22` to avoid clipping the finder patterns.

**Text watermark:**

```php
$svg = QrCode::data('https://laika.dev')
    ->watermarkText('LAIKA')
    ->svg();

// Styled
$svg = QrCode::data('https://laika.dev')
    ->watermarkText('★', [
        'watermark_size'   => 0.20,
        'watermark_bg'     => '#1a1a2e',
        'watermark_color'  => '#ffffff',
        'watermark_radius' => 50,
    ])
    ->svg();
```

**Image/logo watermark:**

```php
// From file path
$png = QrCode::data('https://laika.dev')
    ->watermarkImage('/path/to/logo.png')
    ->png();

// From base64 data URI
$svg = QrCode::data('https://laika.dev')
    ->watermarkImage('data:image/png;base64,...')
    ->svg();
```

**Combining title, footer and watermark:**

```php
$png = QrCode::data('HF5H65AP')
    ->watermarkText('LAIKA')
    ->options([
        'title'        => 'Serial Number',
        'title_color'  => '#003366',
        'footer'       => 'HF5H65AP — laika.dev',
        'footer_color' => '#666666',
    ])
    ->png();
```

### All QR Options

**Base:**

| Option        | Type     | Default     | Description                                     |
|---------------|----------|-------------|-------------------------------------------------|
| `module_size` | `int`    | `8`         | Pixels per module                               |
| `margin`      | `int`    | `4`         | Quiet-zone modules (minimum 4 per QR spec)      |
| `color`       | `string` | `#000000`   | Dark module colour (hex)                        |
| `bg`          | `string` | `#ffffff`   | Background colour, `''` = transparent           |

**Title & footer:**

| Option         | Type     | Default         | Description                           |
|----------------|----------|-----------------|---------------------------------------|
| `title`        | `string` | `''`            | Text printed above the QR code        |
| `title_size`   | `int`    | `0` (auto)      | Font size in px                       |
| `title_color`  | `string` | same as `color` | Title text colour                     |
| `title_align`  | `string` | `'center'`      | `'left'` \| `'center'` \| `'right'`  |
| `footer`       | `string` | `''`            | Text printed below the QR code        |
| `footer_size`  | `int`    | `0` (auto)      | Font size in px                       |
| `footer_color` | `string` | same as `color` | Footer text colour                    |
| `footer_align` | `string` | `'center'`      | `'left'` \| `'center'` \| `'right'`  |

**Center watermark:**

| Option              | Type     | Default     | Description                                         |
|---------------------|----------|-------------|-----------------------------------------------------|
| `watermark_text`    | `string` | `''`        | Text in the center box                              |
| `watermark_image`   | `string` | `''`        | File path or `data:image/...;base64,...` URI         |
| `watermark_size`    | `float`  | `0.20`      | Fraction of QR canvas size (max `0.22` recommended) |
| `watermark_bg`      | `string` | `#ffffff`   | Background box colour                               |
| `watermark_color`   | `string` | `#000000`   | Text colour                                         |
| `watermark_font`    | `int`    | `0`         | Font size in px, `0` = auto-fit                     |
| `watermark_radius`  | `int`    | `4`         | Corner radius of box (SVG only)                     |
| `watermark_padding` | `int`    | `6`         | Padding inside box in px                            |

### Raw Matrix

```php
// Returns bool[][] — true = dark module
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
                $bar['width'] * 2, $color
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