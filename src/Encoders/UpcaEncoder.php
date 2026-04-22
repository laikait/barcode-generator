<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

/**
 * UPC-A barcode encoder.
 *
 * Accepts 11 digits (check digit auto-computed) or 12 digits (check digit verified).
 * UPC-A is essentially EAN-13 with a leading 0 prepended.
 */
class UpcaEncoder extends AbstractEncoder
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

        // Left group: digits 0–5
        for ($i = 0; $i < 6; $i++) {
            $binary .= self::L[(int) $data[$i]];
        }

        $binary .= self::GUARD_CENTER;

        // Right group: digits 6–11
        for ($i = 6; $i < 12; $i++) {
            $binary .= self::R[(int) $data[$i]];
        }

        $binary .= self::GUARD_NORMAL;

        return $this->binaryToBar($binary);
    }

    public function validate(string $data): bool
    {
        $len = strlen($data);

        if ($len !== 11 && $len !== 12) {
            return false;
        }

        if (!ctype_digit($data)) {
            return false;
        }

        if ($len === 12) {
            return $this->checkDigit(substr($data, 0, 11)) === (int) $data[11];
        }

        return true;
    }

    public function label(string $data): string
    {
        return $this->normalise($data);
    }

    private function normalise(string $data): string
    {
        return strlen($data) === 11 ? $data . $this->checkDigit($data) : $data;
    }

    private function checkDigit(string $eleven): int
    {
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += (int) $eleven[$i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
