<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;

/**
 * One downloadable file of a resource, for example the original XLSX or its CSV and JSON-LD conversions.
 */
final readonly class ResourceFile
{
    public function __construct(
        public string $format,
        public string $downloadUrl,
        public ?int $fileSize,
        public ?int $opennessScore,
        public ?string $compressedFormat,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $format = Values::string($data['format'] ?? null);
        $url = Values::string($data['download_url'] ?? null);

        if ($format === null || $url === null) {
            return null;
        }

        return new self(
            format: strtolower($format),
            downloadUrl: $url,
            fileSize: Values::int($data['file_size'] ?? null),
            opennessScore: Values::int($data['openness_score'] ?? null),
            compressedFormat: Values::string($data['compressed_file_format'] ?? null),
        );
    }
}
