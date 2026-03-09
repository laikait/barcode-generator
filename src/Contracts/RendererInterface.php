<?php

declare(strict_types=1);

namespace Laika\Barcode\Contracts;

interface RendererInterface
{
    /**
     * Render a barcode from the given bar pattern.
     *
     * @param  array<int, array{bar: bool, width: int}>  $bars
     * @param  string  $label  Human-readable label printed below the barcode.
     * @param  array<string, mixed>  $options
     */
    public function render(array $bars, string $label, array $options = []): string;
}
