<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

/**
 * File of a resource saved to disk.
 */
final readonly class DownloadedFile
{
    public function __construct(
        public string $path,
        public int $bytes,
        public ?string $contentType,
        public ?string $format,
        public string $url,
    ) {}
}
