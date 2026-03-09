<?php

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

use Laika\Barcode\Exceptions\InvalidDataException;
use Laika\Barcode\Qr\ReedSolomon;

/**
 * QR Code encoder (versions 1–10, byte mode, all four EC levels).
 *
 * Returns a bool[][] matrix where true = dark module.
 */
final class QrEncoder
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** EC level string → internal table index */
    private const EC_IDX = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];

    /**
     * EC indicator bits for format information.
     * L=01(1), M=00(0), Q=11(3), H=10(2)
     */
    private const EC_IND = [1, 0, 3, 2];

    /**
     * Error-correction table per version (1–10) and EC level (L,M,Q,H).
     *
     * Format: [ec_codewords_per_block, [[block_count, data_codewords_per_block], ...]]
     */
    private const EC_TABLE = [
        1  => [[7,  [[1, 19]]],              [10, [[1, 16]]],              [13, [[1, 13]]],              [17, [[1,  9]]]],
        2  => [[10, [[1, 34]]],              [16, [[1, 28]]],              [22, [[1, 22]]],              [28, [[1, 16]]]],
        3  => [[15, [[1, 55]]],              [26, [[1, 44]]],              [18, [[2, 17]]],              [22, [[2, 13]]]],
        4  => [[20, [[1, 80]]],              [18, [[2, 32]]],              [26, [[2, 24]]],              [16, [[4,  9]]]],
        5  => [[26, [[1, 108]]],             [24, [[2, 43]]],              [18, [[2, 15], [2, 16]]],     [22, [[2, 11], [2, 12]]]],
        6  => [[18, [[2, 68]]],              [16, [[4, 27]]],              [24, [[4, 19]]],              [28, [[4, 15]]]],
        7  => [[20, [[2, 78]]],              [18, [[4, 31]]],              [18, [[2, 14], [4, 15]]],     [26, [[4, 13], [1, 14]]]],
        8  => [[24, [[2, 97]]],              [22, [[2, 38], [2, 39]]],     [22, [[4, 18], [2, 19]]],     [26, [[4, 14], [2, 15]]]],
        9  => [[30, [[2, 116]]],             [22, [[3, 36], [2, 37]]],     [20, [[4, 16], [4, 17]]],     [24, [[4, 12], [4, 13]]]],
        10 => [[18, [[2, 68], [2, 69]]],     [26, [[4, 43], [1, 44]]],     [24, [[6, 19], [2, 20]]],     [28, [[6, 15], [2, 16]]]],
    ];

    /**
     * Alignment pattern center coordinates per version.
     * All combinations of these values are used (except finder-pattern overlaps).
     */
    private const ALIGN = [
        1  => [],
        2  => [6, 18],
        3  => [6, 22],
        4  => [6, 26],
        5  => [6, 30],
        6  => [6, 34],
        7  => [6, 22, 38],
        8  => [6, 24, 42],
        9  => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * Remainder (filler) bits appended after data bits.
     * Versions 2–6 require 7; versions 1, 7–10 require 0.
     */
    private const REMAINDER = [1=>0, 2=>7, 3=>7, 4=>7, 5=>7, 6=>7, 7=>0, 8=>0, 9=>0, 10=>0];

    /**
     * Format info bit positions for copy 1 (top-left area),
     * ordered from bit 14 (MSB) down to bit 0 (LSB).
     */
    private const FORMAT_POS1 = [
        [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8],
        [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0],
    ];

    // =========================================================================
    // Public entry point
    // =========================================================================

    /**
     * Encode $data as a QR code and return a bool[][] matrix.
     *
     * @param  string  $ecLevel  'L', 'M', 'Q', or 'H'
     * @return bool[][]          true = dark module
     *
     * @throws InvalidDataException
     */
    public function encode(string $data, string $ecLevel = 'M'): array
    {
        $ec = strtoupper(trim($ecLevel));

        if (!isset(self::EC_IDX[$ec])) {
            throw new InvalidDataException("Invalid EC level '$ec'. Use L, M, Q, or H.");
        }

        $ecIdx   = self::EC_IDX[$ec];
        $version = $this->selectVersion(strlen($data), $ecIdx);
        $size    = 4 * $version + 17;

        // --- Data pipeline ---
        $dataCW = $this->encodeData($data, $version, $ecIdx);
        $allCW  = $this->blocksAndInterleave($dataCW, $version, $ecIdx);

        // Codewords → flat bit array
        $cwBits = [];
        foreach ($allCW as $cw) {
            for ($i = 7; $i >= 0; $i--) {
                $cwBits[] = ($cw >> $i) & 1;
            }
        }
        for ($i = 0, $rem = self::REMAINDER[$version]; $i < $rem; $i++) {
            $cwBits[] = 0;
        }

        // --- Matrix: function patterns (shared across all mask trials) ---
        $base = $this->buildBase($size, $version);

        // --- Evaluate all 8 masks, keep the one with lowest penalty ---
        $bestScore = PHP_INT_MAX;
        $bestMask  = 0;
        $bestMat   = null;

        for ($mask = 0; $mask < 8; $mask++) {
            $m = $base;
            $this->placeData($m, $cwBits, $size, $mask);
            $this->placeFormatInfo($m, $ecIdx, $mask, $size);

            $score = $this->penalty($m, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask  = $mask;
                $bestMat   = $m;
            }
        }

        // Convert int[][] → bool[][]
        $result = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                $result[$r][$c] = ($bestMat[$r][$c] === 1);
            }
        }

        return $result;
    }

    // =========================================================================
    // Version selection
    // =========================================================================

    private function selectVersion(int $byteLen, int $ecIdx): int
    {
        foreach (self::EC_TABLE as $version => $levels) {
            // Byte mode overhead: 4-bit mode indicator + 8-bit (v1–9) or 16-bit (v10+) count
            $overhead   = 4 + ($version <= 9 ? 8 : 16);
            $bitsNeeded = $overhead + $byteLen * 8;
            $capacity   = $this->dataCW($version, $ecIdx) * 8;

            if ($capacity >= $bitsNeeded) {
                return $version;
            }
        }

        $maxV = max(array_keys(self::EC_TABLE));
        $ecStr = array_search($ecIdx, self::EC_IDX);
        throw new InvalidDataException(
            "Data too long for QR version 1–{$maxV} with EC level {$ecStr}."
        );
    }

    /** Total data codewords for a given version/EC combination. */
    private function dataCW(int $version, int $ecIdx): int
    {
        $total = 0;
        foreach (self::EC_TABLE[$version][$ecIdx][1] as [$blocks, $dc]) {
            $total += $blocks * $dc;
        }
        return $total;
    }

    // =========================================================================
    // Data encoding (byte mode)
    // =========================================================================

    /** @return int[] Data codeword integers (0–255). */
    private function encodeData(string $data, int $version, int $ecIdx): array
    {
        $len      = strlen($data);
        $totalDC  = $this->dataCW($version, $ecIdx);
        $maxBits  = $totalDC * 8;
        $bits     = [];

        // Mode indicator: byte = 0100
        array_push($bits, 0, 1, 0, 0);

        // Character count indicator
        $countBits = $version <= 9 ? 8 : 16;
        for ($i = $countBits - 1; $i >= 0; $i--) {
            $bits[] = ($len >> $i) & 1;
        }

        // Data bytes
        for ($i = 0; $i < $len; $i++) {
            $byte = ord($data[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        // Terminator (up to 4 zero bits)
        $termLen = min(4, $maxBits - count($bits));
        for ($i = 0; $i < $termLen; $i++) {
            $bits[] = 0;
        }

        // Pad to byte boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad codewords: alternating 0xEC and 0x11
        $padBytes = [0xEC, 0x11];
        $padIdx   = 0;
        while (count($bits) < $maxBits) {
            $pb = $padBytes[$padIdx++ % 2];
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($pb >> $j) & 1;
            }
        }

        // Bits → codewords
        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $cw = 0;
            for ($j = 0; $j < 8; $j++) {
                $cw = ($cw << 1) | $bits[$i + $j];
            }
            $codewords[] = $cw;
        }

        return $codewords;
    }

    // =========================================================================
    // Error correction blocks and interleaving
    // =========================================================================

    /** @param int[] $dataCW @return int[] All interleaved codewords (data + EC). */
    private function blocksAndInterleave(array $dataCW, int $version, int $ecIdx): array
    {
        [$ecpb, $blockDefs] = self::EC_TABLE[$version][$ecIdx];
        $gen = ReedSolomon::generatorPoly($ecpb);

        $dataBlocks = [];
        $ecBlocks   = [];
        $offset     = 0;

        foreach ($blockDefs as [$blockCount, $dc]) {
            for ($b = 0; $b < $blockCount; $b++) {
                $block        = array_slice($dataCW, $offset, $dc);
                $dataBlocks[] = $block;
                $ecBlocks[]   = ReedSolomon::remainder($block, $gen);
                $offset      += $dc;
            }
        }

        // Interleave data codewords (column-major across blocks)
        $result = [];
        $maxDC  = max(array_map('count', $dataBlocks));

        for ($i = 0; $i < $maxDC; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        // Interleave EC codewords
        for ($i = 0; $i < $ecpb; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // Matrix construction
    // =========================================================================

    /**
     * Build the base matrix (function patterns only).
     * Free cells = -1, dark function = 1, light function = 0.
     *
     * @return int[][]
     */
    private function buildBase(int $size, int $version): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, -1));

        $this->placeFinderPattern($m, 0, 0, $size);
        $this->placeFinderPattern($m, 0, $size - 7, $size);
        $this->placeFinderPattern($m, $size - 7, 0, $size);
        $this->placeTiming($m, $size);
        $this->placeAlignment($m, $version, $size);
        // Dark module (always 1, never masked)
        $m[4 * $version + 9][8] = 1;
        // Reserve format-information areas
        $this->reserveFormat($m, $size);

        return $m;
    }

    /**
     * Place a 7×7 finder pattern centred at ($row, $col), plus its 1-module separator.
     */
    private function placeFinderPattern(array &$m, int $row, int $col, int $size): void
    {
        for ($dr = -1; $dr <= 7; $dr++) {
            for ($dc = -1; $dc <= 7; $dc++) {
                $r = $row + $dr;
                $c = $col + $dc;
                if ($r < 0 || $r >= $size || $c < 0 || $c >= $size) {
                    continue;
                }

                if ($dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6) {
                    // Inside 7×7 finder
                    $dark  = ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6
                           || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4));
                    $m[$r][$c] = $dark ? 1 : 0;
                } elseif ($m[$r][$c] === -1) {
                    // Separator (1 module wide, always light)
                    $m[$r][$c] = 0;
                }
            }
        }
    }

    private function placeTiming(array &$m, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            $v = ($i % 2 === 0) ? 1 : 0;
            if ($m[6][$i] === -1) {
                $m[6][$i] = $v;
            }
            if ($m[$i][6] === -1) {
                $m[$i][6] = $v;
            }
        }
    }

    private function placeAlignment(array &$m, int $version, int $size): void
    {
        $coords = self::ALIGN[$version] ?? [];
        if (empty($coords)) {
            return;
        }

        foreach ($coords as $r) {
            foreach ($coords as $c) {
                // Skip if centre is already occupied (overlaps finder pattern)
                if ($m[$r][$c] !== -1) {
                    continue;
                }

                // 5×5 alignment pattern
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $dark = (abs($dr) === 2 || abs($dc) === 2 || ($dr === 0 && $dc === 0));
                        $m[$r + $dr][$c + $dc] = $dark ? 1 : 0;
                    }
                }
            }
        }
    }

    /**
     * Mark format-information modules as reserved (set to 0 so data placement skips them).
     * They will be overwritten with actual format bits later.
     */
    private function reserveFormat(array &$m, int $size): void
    {
        // Around top-left finder: row 8 (cols 0–8) and col 8 (rows 0–8)
        for ($i = 0; $i <= 8; $i++) {
            if ($m[8][$i] === -1) {
                $m[8][$i] = 0;
            }
            if ($m[$i][8] === -1) {
                $m[$i][8] = 0;
            }
        }
        // Near top-right finder: row 8 (cols size-8 to size-1)
        for ($i = $size - 8; $i < $size; $i++) {
            if ($m[8][$i] === -1) {
                $m[8][$i] = 0;
            }
        }
        // Near bottom-left finder: col 8 (rows size-7 to size-1)
        for ($i = $size - 7; $i < $size; $i++) {
            if ($m[$i][8] === -1) {
                $m[$i][8] = 0;
            }
        }
    }

    // =========================================================================
    // Data placement (zigzag with mask)
    // =========================================================================

    /** @param int[] $bits */
    private function placeData(array &$m, array $bits, int $size, int $mask): void
    {
        $bitIdx = 0;
        $total  = count($bits);
        $upward = true;
        $col    = $size - 1;

        while ($col >= 0) {
            if ($col === 6) {
                // Skip timing column
                $col--;
                continue;
            }

            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? ($size - 1 - $step) : $step;

                for ($dc = 0; $dc <= 1; $dc++) {
                    $c = $col - $dc;
                    if ($m[$row][$c] !== -1) {
                        continue;
                    }

                    $bit         = ($bitIdx < $total) ? $bits[$bitIdx++] : 0;
                    $m[$row][$c] = $bit ^ ($this->maskFn($mask, $row, $c) ? 1 : 0);
                }
            }

            $upward = !$upward;
            $col   -= 2;
        }
    }

    private function maskFn(int $mask, int $r, int $c): bool
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0,
            1 => $r % 2 === 0,
            2 => $c % 3 === 0,
            3 => ($r + $c) % 3 === 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
            5 => (($r * $c) % 2 + ($r * $c) % 3) === 0,
            6 => (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0,
            7 => (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0,
            default => false,
        };
    }

    // =========================================================================
    // Format information
    // =========================================================================

    private function placeFormatInfo(array &$m, int $ecIdx, int $mask, int $size): void
    {
        $format = $this->formatBits(self::EC_IND[$ecIdx], $mask);

        // Copy 1: around top-left finder
        // pos1[i] receives bit (14 - i)
        foreach (self::FORMAT_POS1 as $i => [$r, $c]) {
            $m[$r][$c] = ($format >> (14 - $i)) & 1;
        }

        // Copy 2 near top-right finder: row 8, bits 7→0 placed at cols size-1 → size-8
        for ($i = 0; $i < 8; $i++) {
            $m[8][$size - 1 - $i] = ($format >> $i) & 1;
        }

        // Copy 2 near bottom-left finder: col 8, bits 8→14 placed at rows size-1 → size-7
        for ($i = 0; $i < 7; $i++) {
            $m[$size - 1 - $i][8] = ($format >> (8 + $i)) & 1;
        }
    }

    /**
     * Compute the 15-bit format information word.
     *
     * Uses BCH(15,5) with generator G(x) = x^10+x^8+x^5+x^4+x^2+x+1,
     * then XORs with the format mask 101010000010010.
     */
    private function formatBits(int $ecIndicator, int $mask): int
    {
        $data = ($ecIndicator << 3) | $mask; // 5-bit data field
        $gen  = 0b10100110111;               // generator polynomial

        // Polynomial long division: compute remainder of (data << 10) / gen
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= ($gen << ($i - 10));
            }
        }

        return (($data << 10) | ($rem & 0x3FF)) ^ 0b101010000010010;
    }

    // =========================================================================
    // Penalty scoring (4 rules from QR spec)
    // =========================================================================

    private function penalty(array $m, int $size): int
    {
        return $this->pen1($m, $size)
             + $this->pen2($m, $size)
             + $this->pen3($m, $size)
             + $this->pen4($m, $size);
    }

    /** Rule 1: 5+ consecutive same-colour modules in a row or column. */
    private function pen1(array $m, int $size): int
    {
        $score = 0;

        for ($i = 0; $i < $size; $i++) {
            // Horizontal
            $run = 1;
            for ($j = 1; $j < $size; $j++) {
                if ($m[$i][$j] === $m[$i][$j - 1]) {
                    $run++;
                    if ($run === 5) {
                        $score += 3;
                    } elseif ($run > 5) {
                        $score += 1;
                    }
                } else {
                    $run = 1;
                }
            }
            // Vertical
            $run = 1;
            for ($j = 1; $j < $size; $j++) {
                if ($m[$j][$i] === $m[$j - 1][$i]) {
                    $run++;
                    if ($run === 5) {
                        $score += 3;
                    } elseif ($run > 5) {
                        $score += 1;
                    }
                } else {
                    $run = 1;
                }
            }
        }

        return $score;
    }

    /** Rule 2: 2×2 blocks of the same colour. */
    private function pen2(array $m, int $size): int
    {
        $score = 0;

        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1]
                    && $v === $m[$r + 1][$c]
                    && $v === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        return $score;
    }

    /** Rule 3: Patterns resembling the finder pattern. */
    private function pen3(array $m, int $size): int
    {
        $score = 0;
        $p1    = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $p2    = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];

        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 11; $j++) {
                $h1 = $h2 = $v1 = $v2 = true;
                for ($k = 0; $k < 11; $k++) {
                    if ($m[$i][$j + $k] !== $p1[$k]) {
                        $h1 = false;
                    }
                    if ($m[$i][$j + $k] !== $p2[$k]) {
                        $h2 = false;
                    }
                    if ($m[$j + $k][$i] !== $p1[$k]) {
                        $v1 = false;
                    }
                    if ($m[$j + $k][$i] !== $p2[$k]) {
                        $v2 = false;
                    }
                }
                if ($h1 || $h2) {
                    $score += 40;
                }
                if ($v1 || $v2) {
                    $score += 40;
                }
            }
        }

        return $score;
    }

    /** Rule 4: Dark/light module ratio deviation from 50 %. */
    private function pen4(array $m, int $size): int
    {
        $dark  = 0;
        $total = $size * $size;

        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($m[$r][$c] === 1) {
                    $dark++;
                }
            }
        }

        $pct  = ($dark * 100) / $total;
        $prev = (int) (floor($pct / 5) * 5);
        $next = $prev + 5;

        return (int) (min(abs($prev - 50), abs($next - 50)) / 5 * 10);
    }
}
