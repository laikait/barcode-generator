<?php

declare(strict_types=1);

namespace Laika\Barcode\Contracts;

interface BarcodeInterface
{
    /**
     * Encode the given data into a barcode bar pattern.
     *
     * @param  string  $data  The raw data to encode.
     * @return array<int, array{bar: bool, width: int}>  Array of bar/space entries.
     *
     * @throws \Laika\Barcode\Exceptions\InvalidDataException
     */
    public function encode(string $data): array;

    /**
     * Validate that the given data is compatible with this barcode type.
     */
    public function validate(string $data): bool;

    /**
     * Return the human-readable label (the data string or check digit appended).
     */
    public function label(string $data): string;
}
