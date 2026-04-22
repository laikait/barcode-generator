<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

/**
 * Code 39 encoder.
 *
 * Each character is encoded as 5 bars and 4 spaces (9 elements total).
 * Narrow = 1 module, Wide = 3 modules.  Inter-character gap = 1 module (space).
 */
class Code39Encoder extends AbstractEncoder
{
    /** Encoding: 0 = narrow, 1 = wide  (BSBSBSBSB order) */
    private const CHARSET = [
        '0' => '000110100', '1' => '100100001', '2' => '001100001',
        '3' => '101100000', '4' => '000110001', '5' => '100110000',
        '6' => '001110000', '7' => '000100101', '8' => '100100100',
        '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
        'C' => '101001000', 'D' => '000011001', 'E' => '100011000',
        'F' => '001011000', 'G' => '000001101', 'H' => '100001100',
        'I' => '001001100', 'J' => '000011100', 'K' => '100000011',
        'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
        'O' => '100010010', 'P' => '001010010', 'Q' => '000000111',
        'R' => '100000110', 'S' => '001000110', 'T' => '000010110',
        'U' => '110000001', 'V' => '011000001', 'W' => '111000000',
        'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
        '-' => '010000101', '.' => '110000100', ' ' => '011000100',
        '$' => '010101000', '/' => '010100010', '+' => '010001010',
        '%' => '000101010', '*' => '010010100',
    ];

    private const NARROW = 1;
    private const WIDE   = 3;
    private const GAP    = 1; // inter-character space

    public function encode(string $data): array
    {
        $this->assertValid($data);

        $data   = strtoupper($data);
        $binary = $this->encodedChar('*');   // start

        foreach (str_split($data) as $char) {
            $binary .= str_repeat('0', self::GAP);  // inter-char gap
            $binary .= $this->encodedChar($char);
        }

        $binary .= str_repeat('0', self::GAP);
        $binary .= $this->encodedChar('*');   // stop

        return $this->binaryToBar($binary);
    }

    public function validate(string $data): bool
    {
        if (strlen($data) === 0) {
            return false;
        }

        foreach (str_split(strtoupper($data)) as $char) {
            if (!isset(self::CHARSET[$char])) {
                return false;
            }
        }

        return true;
    }

    public function label(string $data): string
    {
        return strtoupper($data);
    }

    /** Convert a Code39 character definition to a binary module string. */
    private function encodedChar(string $char): string
    {
        $pattern = self::CHARSET[$char];
        $binary  = '';

        foreach (str_split($pattern) as $i => $bit) {
            // Even indices = bars, odd indices = spaces
            $modules = $bit === '1' ? self::WIDE : self::NARROW;
            $binary .= str_repeat($i % 2 === 0 ? '1' : '0', $modules);
        }

        return $binary;
    }
}
