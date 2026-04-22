<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

/**
 * EAN-13 barcode encoder.
 *
 * Accepts 12 digits (check digit auto-computed) or 13 digits (check digit verified).
 */
class Ean13Encoder extends AbstractEncoder
{
    // Left-hand digit patterns (L-code and G-code)
    private const L = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
    private const G = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
    // Right-hand digit patterns (R-code)
    private const R = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];

    // First-digit structure table: 0=L, 1=G
    private const STRUCTURE = [
        '000000','001011','001101','001110','010011',
        '011001','011100','010101','010110','011010',
    ];

    private const GUARD_NORMAL  = '101';
    private const GUARD_CENTER  = '01010';
    private const GUARD_SPECIAL = '01010'; // end same as center

    public function encode(string $data): array
    {
        $this->assertValid($data);
        $data = $this->normalise($data);

        $firstDigit = (int) $data[0];
        $structure  = self::STRUCTURE[$firstDigit];

        $binary  = self::GUARD_NORMAL;

        // Left group (digits 2–7, indices 1–6)
        for ($i = 1; $i <= 6; $i++) {
            $d       = (int) $data[$i];
            $useG    = $structure[$i - 1] === '1';
            $binary .= $useG ? self::G[$d] : self::L[$d];
        }

        $binary .= self::GUARD_CENTER;

        // Right group (digits 8–13, indices 7–12)
        for ($i = 7; $i <= 12; $i++) {
            $binary .= self::R[(int) $data[$i]];
        }

        $binary .= self::GUARD_NORMAL;

        return $this->binaryToBar($binary);
    }

    public function validate(string $data): bool
    {
        $len = strlen($data);

        if ($len !== 12 && $len !== 13) {
            return false;
        }

        if (!ctype_digit($data)) {
            return false;
        }

        if ($len === 13) {
            return $this->checkDigit(substr($data, 0, 12)) === (int) $data[12];
        }

        return true;
    }

    public function label(string $data): string
    {
        return $this->normalise($data);
    }

    private function normalise(string $data): string
    {
        if (strlen($data) === 12) {
            return $data . $this->checkDigit($data);
        }

        return $data;
    }

    private function checkDigit(string $twelve): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $twelve[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
