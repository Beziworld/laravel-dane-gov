<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Dataset (zbiór danych): a package of resources published by one institution.
 */
final readonly class Dataset
{
    /**
     * @param list<Category> $categories
     * @param list<string> $keywords
     * @param list<string> $formats
     * @param list<string> $types
     * @param list<int> $opennessScores
     * @param list<string> $regions
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public int $id,
        public ?string $slug,
        public string $title,
        public ?string $notes,
        public ?Category $category,
        public array $categories,
        public array $keywords,
        public array $formats,
        public array $types,
        public array $opennessScores,
        public ?string $licenseName,
        public ?string $updateFrequency,
        public ?string $url,
        public array $regions,
        public ?int $institutionId,
        public ?int $resourcesCount,
        public ?int $showcasesCount,
        public ?int $viewsCount,
        public ?int $downloadsCount,
        public ?bool $hasHighValueData,
        public ?bool $hasDynamicData,
        public ?bool $hasResearchData,
        public ?CarbonImmutable $created,
        public ?CarbonImmutable $modified,
        public ?CarbonImmutable $verified,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource JSON:API resource object of type "dataset".
     */
    public static function fromResource(array $resource): self
    {
        $attributes = Values::map($resource['attributes'] ?? null);
        $category = Values::map($attributes['category'] ?? null);

        return new self(
            id: Values::int($resource['id'] ?? null) ?? 0,
            slug: Values::string($attributes['slug'] ?? null),
            title: Values::text($attributes['title'] ?? null) ?? '',
            notes: Values::string($attributes['notes'] ?? null),
            category: $category !== [] ? Category::fromArray($category) : null,
            categories: array_values(array_map(
                static fn(mixed $item): Category => Category::fromArray(Values::map($item)),
                array_filter(is_array($attributes['categories'] ?? null) ? $attributes['categories'] : [], is_array(...)),
            )),
            keywords: Values::stringList($attributes['keywords'] ?? []),
            formats: Values::stringList($attributes['formats'] ?? []),
            types: Values::stringList($attributes['types'] ?? []),
            opennessScores: Values::intList($attributes['openness_scores'] ?? []),
            licenseName: Values::string($attributes['license_name'] ?? null),
            updateFrequency: Values::string($attributes['update_frequency'] ?? null),
            url: Values::string($attributes['url'] ?? null),
            regions: self::regions($attributes['regions'] ?? null),
            institutionId: Values::relatedId($resource, 'institution'),
            resourcesCount: Values::relatedCount($resource, 'resources'),
            showcasesCount: Values::relatedCount($resource, 'showcases'),
            viewsCount: Values::int($attributes['views_count'] ?? null),
            downloadsCount: Values::int($attributes['downloads_count'] ?? null),
            hasHighValueData: Values::bool($attributes['has_high_value_data'] ?? null),
            hasDynamicData: Values::bool($attributes['has_dynamic_data'] ?? null),
            hasResearchData: Values::bool($attributes['has_research_data'] ?? null),
            created: Values::date($attributes['created'] ?? null),
            modified: Values::date($attributes['modified'] ?? null),
            verified: Values::date($attributes['verified'] ?? null),
            raw: $resource,
        );
    }

    /**
     * Notes without HTML markup.
     */
    public function plainNotes(): ?string
    {
        return Values::text($this->notes);
    }

    /**
     * Public page of the dataset on dane.gov.pl.
     */
    public function portalUrl(): string
    {
        return sprintf('https://dane.gov.pl/pl/dataset/%d%s', $this->id, $this->slug !== null ? ',' . $this->slug : '');
    }

    /**
     * @return list<string>
     */
    private static function regions(mixed $regions): array
    {
        $names = [];

        foreach (is_array($regions) ? $regions : [] as $region) {
            $name = is_array($region) ? Values::string($region['name'] ?? null) : null;

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
