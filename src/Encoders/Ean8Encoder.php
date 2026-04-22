<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

/**
 * EAN-8 barcode encoder.
 *
 * Accepts 7 digits (check digit auto-computed) or 8 digits (check digit verified).
 */
class Ean8Encoder extends AbstractEncoder
{
    private const L = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
    private const R = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];

    private const GUARD_NORMAL = '101';
    private const GUARD_CENTER = '01010';

    public function encode(string $data): array
    {
        $this->assertValid($data);
        $data = $this->normalise($data);

        $binary = self::GUARD_NORMAL;

        // Left group: digits 0–3
        for ($i = 0; $i < 4; $i++) {
            $binary .= self::L[(int) $data[$i]];
        }

        $binary .= self::GUARD_CENTER;

        // Right group: digits 4–7
        for ($i = 4; $i < 8; $i++) {
            $binary .= self::R[(int) $data[$i]];
        }

        $binary .= self::GUARD_NORMAL;

        return $this->binaryToBar($binary);
    }

    public function validate(string $data): bool
    {
        $len = strlen($data);

        if ($len !== 7 && $len !== 8) {
            return false;
        }

        if (!ctype_digit($data)) {
            return false;
        }

        if ($len === 8) {
            return $this->checkDigit(substr($data, 0, 7)) === (int) $data[7];
        }

        return true;
    }

    public function label(string $data): string
    {
        return $this->normalise($data);
    }

    private function normalise(string $data): string
    {
        return strlen($data) === 7 ? $data . $this->checkDigit($data) : $data;
    }

    private function checkDigit(string $seven): int
    {
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $seven[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
