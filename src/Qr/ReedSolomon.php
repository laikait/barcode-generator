<?php
/**
 * Laika Barcode/QR Code Generator
 * Author: Showket Ahmed
 * Email: strblackhawk@gmail.com
 */

declare(strict_types=1);

namespace Laika\Barcode\Qr;

/**
 * Galois Field GF(256) arithmetic for Reed-Solomon error correction.
 *
 * Primitive polynomial: x^8 + x^4 + x^3 + x^2 + 1  (0x11D)
 */
final class ReedSolomon
{
    /** @var int[] */
    private static array $exp = [];
    /** @var int[] */
    private static array $log = [];
    private static bool  $ready = false;

    // -------------------------------------------------------------------------
    // Initialisation
    // -------------------------------------------------------------------------

    private static function init(): void
    {
        if (self::$ready) {
            return;
        }

        self::$ready = true;
        self::$exp   = array_fill(0, 512, 0);
        self::$log   = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x >= 256) {
                $x ^= 0x11D;
            }
        }
        // Extend exp table to avoid modular index wrap-around in multiply
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /** Multiply two GF(256) elements. */
    public static function mul(int $a, int $b): int
    {
        self::init();

        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /**
     * Build the generator polynomial for $n error-correction codewords.
     *
     * g(x) = (x - α^0)(x - α^1)...(x - α^(n-1))
     *
     * @return int[]  Coefficients from highest to lowest degree.
     */
    public static function generatorPoly(int $n): array
    {
        self::init();

        $poly = [1];

        for ($i = 0; $i < $n; $i++) {
            $alpha = self::$exp[$i];
            $next  = array_fill(0, count($poly) + 1, 0);

            foreach ($poly as $j => $coeff) {
                $next[$j]     ^= $coeff;
                $next[$j + 1] ^= self::mul($coeff, $alpha);
            }

            $poly = $next;
        }

        return $poly;
    }

    /**
     * Compute the EC remainder (polynomial division).
     *
     * @param  int[]  $data       Data codewords (dividend).
     * @param  int[]  $generator  Generator polynomial coefficients.
     * @return int[]              EC codewords (remainder).
     */
    public static function remainder(array $data, array $generator): array
    {
        self::init();

        $ecLen  = count($generator) - 1;
        $buf    = array_merge($data, array_fill(0, $ecLen, 0));
        $dLen   = count($data);

        for ($i = 0; $i < $dLen; $i++) {
            $lead = $buf[$i];
            if ($lead === 0) {
                continue;
            }

            for ($j = 1; $j <= $ecLen; $j++) {
                $buf[$i + $j] ^= self::mul($generator[$j], $lead);
            }
        }

        return array_slice($buf, $dLen);
    }
}
