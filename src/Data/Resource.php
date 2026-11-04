<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Resource (zasób): a file, a link or an API published within a dataset.
 */
final readonly class Resource
{
    /**
     * @param list<ResourceFile> $files
     * @param list<string> $visualizationTypes
     * @param list<string> $regions
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public int $id,
        public ?string $slug,
        public string $title,
        public ?string $description,
        public ?string $format,
        public ?string $mediaType,
        public ?string $link,
        public ?string $fileUrl,
        public ?string $downloadUrl,
        public ?string $csvDownloadUrl,
        public ?string $jsonldDownloadUrl,
        public ?int $fileSize,
        public array $files,
        public ?int $opennessScore,
        public array $visualizationTypes,
        public array $regions,
        public ?string $language,
        public ?int $datasetId,
        public ?int $institutionId,
        public bool $hasTabularData,
        public ?bool $containsProtectedData,
        public ?int $viewsCount,
        public ?int $downloadsCount,
        public ?CarbonImmutable $dataDate,
        public ?CarbonImmutable $created,
        public ?CarbonImmutable $modified,
        public ?CarbonImmutable $verified,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource JSON:API resource object of type "resource".
     */
    public static function fromResource(array $resource): self
    {
        $attributes = Values::map($resource['attributes'] ?? null);
        $relationships = Values::map($resource['relationships'] ?? null);
        $files = [];

        foreach (is_array($attributes['files'] ?? null) ? $attributes['files'] : [] as $file) {
            $parsed = ResourceFile::fromArray(Values::map($file));

            if ($parsed !== null) {
                $files[] = $parsed;
            }
        }

        $regions = [];

        foreach (is_array($attributes['regions'] ?? null) ? $attributes['regions'] : [] as $region) {
            $name = is_array($region) ? Values::string($region['name'] ?? null) : null;

            if ($name !== null) {
                $regions[] = $name;
            }
        }

        $format = Values::string($attributes['format'] ?? null);

        return new self(
            id: Values::int($resource['id'] ?? null) ?? 0,
            slug: Values::string($attributes['slug'] ?? null),
            title: Values::text($attributes['title'] ?? null) ?? '',
            description: Values::string($attributes['description'] ?? null),
            format: $format !== null ? strtolower($format) : null,
            mediaType: Values::string($attributes['media_type'] ?? null),
            link: Values::string($attributes['link'] ?? null),
            fileUrl: Values::string($attributes['file_url'] ?? null),
            downloadUrl: Values::string($attributes['download_url'] ?? null),
            csvDownloadUrl: Values::string($attributes['csv_download_url'] ?? null),
            jsonldDownloadUrl: Values::string($attributes['jsonld_download_url'] ?? null),
            fileSize: Values::int($attributes['file_size'] ?? null),
            files: $files,
            opennessScore: Values::int($attributes['openness_score'] ?? null),
            visualizationTypes: Values::stringList($attributes['visualization_types'] ?? []),
            regions: $regions,
            language: Values::string($attributes['language'] ?? null),
            datasetId: Values::relatedId($resource, 'dataset'),
            institutionId: Values::relatedId($resource, 'institution'),
            hasTabularData: array_key_exists('tabular_data', $relationships),
            containsProtectedData: Values::bool($attributes['contains_protected_data'] ?? null),
            viewsCount: Values::int($attributes['views_count'] ?? null),
            downloadsCount: Values::int($attributes['downloads_count'] ?? null),
            dataDate: Values::date($attributes['data_date'] ?? null)?->startOfDay(),
            created: Values::date($attributes['created'] ?? null),
            modified: Values::date($attributes['modified'] ?? null),
            verified: Values::date($attributes['verified'] ?? null),
            raw: $resource,
        );
    }

    /**
     * Formats in which the resource can be downloaded.
     *
     * @return list<string>
     */
    public function availableFormats(): array
    {
        $formats = array_map(static fn(ResourceFile $file): string => $file->format, $this->files);

        if ($formats === [] && $this->format !== null && $this->downloadUrl !== null) {
            $formats[] = $this->format;
        }

        return array_values(array_unique($formats));
    }

    /**
     * File of the given format, if published.
     */
    public function file(string $format): ?ResourceFile
    {
        foreach ($this->files as $file) {
            if ($file->format === strtolower($format)) {
                return $file;
            }
        }

        return null;
    }

    public function plainDescription(): ?string
    {
        return Values::text($this->description);
    }
}
