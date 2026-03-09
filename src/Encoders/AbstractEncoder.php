<?php

declare(strict_types=1);

namespace Laika\Barcode\Encoders;

use Laika\Barcode\Contracts\BarcodeInterface;
use Laika\Barcode\Exceptions\InvalidDataException;

abstract class AbstractEncoder implements BarcodeInterface
{
    /**
     * Convert a binary string like "10110" into bar/space entries.
     *
     * @return array<int, array{bar: bool, width: int}>
     */
    protected function binaryToBar(string $binary): array
    {
        $bars  = [];
        $len   = strlen($binary);
        $i     = 0;

        while ($i < $len) {
            $char  = $binary[$i];
            $width = 1;

            while ($i + $width < $len && $binary[$i + $width] === $char) {
                $width++;
            }

            $bars[] = ['bar' => $char === '1', 'width' => $width];
            $i += $width;
        }

        return $bars;
    }

    /**
     * @throws InvalidDataException
     */
    protected function assertValid(string $data): void
    {
        if (!$this->validate($data)) {
            throw new InvalidDataException(
                sprintf('Invalid data for %s: "%s"', static::class, $data)
            );
        }
    }
}
