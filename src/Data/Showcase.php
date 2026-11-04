<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Showcase (przykład wykorzystania): an application, website or analysis built on published data.
 */
final readonly class Showcase
{
    /**
     * @param list<string> $keywords
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public int $id,
        public ?string $slug,
        public string $title,
        public ?string $notes,
        public ?string $category,
        public ?string $categoryName,
        public ?string $author,
        public ?string $url,
        public ?string $imageUrl,
        public array $keywords,
        public ?string $licenseType,
        public ?int $datasetsCount,
        public ?CarbonImmutable $created,
        public ?CarbonImmutable $modified,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource JSON:API resource object of type "showcase".
     */
    public static function fromResource(array $resource): self
    {
        $attributes = Values::map($resource['attributes'] ?? null);

        return new self(
            id: Values::int($resource['id'] ?? null) ?? 0,
            slug: Values::string($attributes['slug'] ?? null),
            title: Values::text($attributes['title'] ?? null) ?? '',
            notes: Values::string($attributes['notes'] ?? null),
            category: Values::string($attributes['category'] ?? null),
            categoryName: Values::string($attributes['category_name'] ?? null),
            author: Values::string($attributes['author'] ?? null),
            url: Values::string($attributes['url'] ?? null),
            imageUrl: Values::string($attributes['image_url'] ?? null),
            keywords: Values::stringList($attributes['keywords'] ?? []),
            licenseType: Values::string($attributes['license_type'] ?? null),
            datasetsCount: Values::relatedCount($resource, 'datasets'),
            created: Values::date($attributes['created'] ?? null),
            modified: Values::date($attributes['modified'] ?? null),
            raw: $resource,
        );
    }
}
