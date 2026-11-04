<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Hit of the cross-model search (/search): a dataset, resource, institution, showcase or article.
 */
final readonly class SearchResult
{
    /**
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public int $id,
        public string $model,
        public string $title,
        public ?string $slug,
        public ?string $notes,
        public ?int $institutionId,
        public ?int $datasetId,
        public ?CarbonImmutable $created,
        public ?CarbonImmutable $modified,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource
     */
    public static function fromResource(array $resource): self
    {
        $attributes = Values::map($resource['attributes'] ?? null);

        return new self(
            id: Values::int($resource['id'] ?? null) ?? 0,
            model: Values::string($attributes['model'] ?? null) ?? Values::string($resource['type'] ?? null) ?? '',
            title: Values::text($attributes['title'] ?? null) ?? '',
            slug: Values::string($attributes['slug'] ?? null),
            notes: Values::text($attributes['notes'] ?? null),
            institutionId: Values::relatedId($resource, 'institution'),
            datasetId: Values::relatedId($resource, 'dataset'),
            created: Values::date($attributes['created'] ?? null),
            modified: Values::date($attributes['modified'] ?? null),
            raw: $resource,
        );
    }
}
