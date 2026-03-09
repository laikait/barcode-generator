<?php

declare(strict_types=1);

namespace Laika\Barcode\Tests;

use Laika\Barcode\Barcode;
use Laika\Barcode\Encoders\Code128Encoder;
use Laika\Barcode\Encoders\Code39Encoder;
use Laika\Barcode\Encoders\Ean13Encoder;
use Laika\Barcode\Encoders\Ean8Encoder;
use Laika\Barcode\Encoders\QrEncoder;
use Laika\Barcode\Encoders\UpcaEncoder;
use Laika\Barcode\Exceptions\BarcodeException;
use Laika\Barcode\Exceptions\InvalidDataException;
use Laika\Barcode\QrCode;
use PHPUnit\Framework\TestCase;

class BarcodeTest extends TestCase
{
    // =========================================================================
    // Code 128
    // =========================================================================

    public function testCode128ValidatesCorrectly(): void
    {
        $enc = new Code128Encoder();
        $this->assertTrue($enc->validate('Hello World'));
        $this->assertTrue($enc->validate('ABC123'));
        $this->assertFalse($enc->validate(''));
    }

    public function testCode128ProducesBars(): void
    {
        $enc  = new Code128Encoder();
        $bars = $enc->encode('TEST');
        $this->assertNotEmpty($bars);
        $this->assertArrayHasKey('bar', $bars[0]);
        $this->assertArrayHasKey('width', $bars[0]);
    }

    public function testCode128ThrowsOnInvalidData(): void
    {
        $this->expectException(InvalidDataException::class);
        (new Code128Encoder())->encode("\x01"); // ASCII control char
    }

    // =========================================================================
    // Code 39
    // =========================================================================

    public function testCode39ValidatesCorrectly(): void
    {
        $enc = new Code39Encoder();
        $this->assertTrue($enc->validate('HELLO-123'));
        $this->assertTrue($enc->validate('hello 123')); // case-insensitive
        $this->assertFalse($enc->validate(''));
        $this->assertFalse($enc->validate('@INVALID'));
    }

    public function testCode39LabelIsUppercase(): void
    {
        $enc = new Code39Encoder();
        $this->assertSame('HELLO', $enc->label('hello'));
    }

    // =========================================================================
    // EAN-13
    // =========================================================================

    public function testEan13AcceptsTwelveDigits(): void
    {
        $enc = new Ean13Encoder();
        $this->assertTrue($enc->validate('590123412345'));
    }

    public function testEan13AcceptsThirteenDigitsWithValidCheck(): void
    {
        $enc = new Ean13Encoder();
        $this->assertTrue($enc->validate('5901234123457'));
    }

    public function testEan13RejectsInvalidCheckDigit(): void
    {
        $enc = new Ean13Encoder();
        $this->assertFalse($enc->validate('5901234123450')); // wrong check
    }

    public function testEan13LabelAppendsCheckDigit(): void
    {
        $enc = new Ean13Encoder();
        $this->assertSame('5901234123457', $enc->label('590123412345'));
    }

    // =========================================================================
    // EAN-8
    // =========================================================================

    public function testEan8AcceptsSevenDigits(): void
    {
        $enc = new Ean8Encoder();
        $this->assertTrue($enc->validate('9638507'));
    }

    public function testEan8LabelAppendsCheckDigit(): void
    {
        $enc   = new Ean8Encoder();
        $label = $enc->label('9638507');
        $this->assertEquals(8, strlen($label));
        $this->assertTrue(ctype_digit($label));
    }

    // =========================================================================
    // UPC-A
    // =========================================================================

    public function testUpcaAcceptsElevenDigits(): void
    {
        $enc = new UpcaEncoder();
        $this->assertTrue($enc->validate('01234567890'));
    }

    public function testUpcaLabelAppendsCheckDigit(): void
    {
        $enc   = new UpcaEncoder();
        $label = $enc->label('01234567890');
        $this->assertEquals(12, strlen($label));
    }

    // =========================================================================
    // Barcode facade
    // =========================================================================

    public function testFacadeReturnsBarcode(): void
    {
        $barcode = Barcode::type('code128');
        $this->assertInstanceOf(Barcode::class, $barcode);
    }

    public function testFacadeThrowsOnUnknownType(): void
    {
        $this->expectException(BarcodeException::class);
        Barcode::type('invalid_type');
    }

    public function testFacadeProducesSvg(): void
    {
        $svg = Barcode::type('code128')->data('Hello')->svg();
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function testFacadeIsValidHelper(): void
    {
        $this->assertTrue(Barcode::type('ean13')->data('590123412345')->isValid());
        $this->assertFalse(Barcode::type('ean13')->data('NOTDIGITS')->isValid());
    }

    public function testSupportedTypesReturnsList(): void
    {
        $types = Barcode::supportedTypes();
        $this->assertContains('code128', $types);
        $this->assertContains('ean13', $types);
    }

    public function testCustomOptionsPassedThrough(): void
    {
        $svg = Barcode::type('code39')
            ->data('ABC')
            ->options(['height' => 50, 'color' => '#ff0000'])
            ->svg();

        $this->assertStringContainsString('#ff0000', $svg);
    }

    // =========================================================================
    // QR Code
    // =========================================================================

    public function testQrMatrixIsSquare(): void
    {
        $matrix = QrCode::data('Hello')->matrix();
        $size   = count($matrix);
        $this->assertGreaterThan(0, $size);
        foreach ($matrix as $row) {
            $this->assertCount($size, $row);
        }
    }

    public function testQrVersion1Is21x21(): void
    {
        // 'Hello' (5 bytes) fits in version 1 with EC=M
        $matrix = QrCode::data('Hello')->ec('M')->matrix();
        $this->assertCount(21, $matrix);
    }

    public function testQrVersion2Is25x25(): void
    {
        // 'Hello, World!' (13 bytes) needs version 2 with EC=Q
        $matrix = QrCode::data('Hello, World!')->ec('Q')->matrix();
        $this->assertCount(25, $matrix);
    }

    public function testQrFinderPatternTopLeft(): void
    {
        $m = QrCode::data('TEST')->ec('M')->matrix();
        // Corners of top-left finder must be dark
        $this->assertTrue($m[0][0]);
        $this->assertTrue($m[0][6]);
        $this->assertTrue($m[6][0]);
        $this->assertTrue($m[6][6]);
        // Inner white ring
        $this->assertFalse($m[1][1]);
        $this->assertFalse($m[1][5]);
        // Inner dark module
        $this->assertTrue($m[3][3]);
    }

    public function testQrTimingPattern(): void
    {
        $m = QrCode::data('TEST')->ec('M')->matrix();
        // Row 6: alternating dark/light starting at col 8
        $this->assertTrue($m[6][8]);   // col 8, even → dark
        $this->assertFalse($m[6][9]);  // col 9, odd  → light
        $this->assertTrue($m[6][10]);
        // Col 6 mirrors row 6
        $this->assertTrue($m[8][6]);
        $this->assertFalse($m[9][6]);
    }

    public function testQrAllFourEcLevels(): void
    {
        foreach (['L', 'M', 'Q', 'H'] as $ec) {
            $matrix = QrCode::data('Laika')->ec($ec)->matrix();
            $this->assertIsArray($matrix, "EC=$ec should return array");
            $this->assertNotEmpty($matrix, "EC=$ec matrix should not be empty");
        }
    }

    public function testQrSvgOutput(): void
    {
        $svg = QrCode::data('https://laika.dev')->svg();
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG must be valid XML');
    }

    public function testQrFacadeOptions(): void
    {
        $svg = QrCode::data('TEST')
            ->ec('L')
            ->options(['module_size' => 8, 'color' => '#ff0000'])
            ->svg();
        $this->assertStringContainsString('#ff0000', $svg);
    }

    public function testQrInvalidEcLevelThrows(): void
    {
        $this->expectException(InvalidDataException::class);
        QrCode::data('test')->ec('X')->matrix();
    }

    public function testQrMatrixContainsBooleans(): void
    {
        $matrix = QrCode::data('test')->matrix();
        foreach ($matrix as $row) {
            foreach ($row as $cell) {
                $this->assertIsBool($cell);
            }
        }
    }

    public function testQrLargerDataSelectsHigherVersion(): void
    {
        $short  = count(QrCode::data('Hi')->ec('M')->matrix());           // small
        $long   = count(QrCode::data(str_repeat('A', 80))->ec('M')->matrix()); // large
        $this->assertGreaterThan($short, $long);
    }
}
