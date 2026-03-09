<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Laika\Barcode\Barcode;

// --- Code 128 SVG ---
$svg = Barcode::type('code128')
    ->data('Hello, Laika!')
    ->options(['height' => 80, 'module_width' => 2])
    ->svg();

file_put_contents(__DIR__ . '/code128.svg', $svg);
echo "✓ code128.svg written\n";

// --- Code 39 SVG ---
$svg = Barcode::type('code39')
    ->data('LAIKA-2025')
    ->svg(['color' => '#1a1a2e', 'height' => 70]);

file_put_contents(__DIR__ . '/code39.svg', $svg);
echo "✓ code39.svg written\n";

// --- EAN-13 (12-digit input, auto check digit) ---
$svg = Barcode::type('ean13')
    ->data('590123412345')
    ->svg();

file_put_contents(__DIR__ . '/ean13.svg', $svg);
echo "✓ ean13.svg written\n";

// --- EAN-8 ---
$svg = Barcode::type('ean8')
    ->data('9638507')
    ->svg();

file_put_contents(__DIR__ . '/ean8.svg', $svg);
echo "✓ ean8.svg written\n";

// --- UPC-A ---
$svg = Barcode::type('upca')
    ->data('01234567890')
    ->svg();

file_put_contents(__DIR__ . '/upca.svg', $svg);
echo "✓ upca.svg written\n";

// --- PNG output (requires GD) ---
if (extension_loaded('gd')) {
    $png = Barcode::type('code128')
        ->data('LAIKA BARCODE')
        ->png(['height' => 80, 'module_width' => 2]);

    file_put_contents(__DIR__ . '/code128.png', $png);
    echo "✓ code128.png written\n";
} else {
    echo "⚠ GD extension not loaded — skipping PNG example\n";
}

echo "\nAll examples generated in " . __DIR__ . "\n";
