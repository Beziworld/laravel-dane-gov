<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;

/**
 * One bucket of a facet (aggregation), for example an institution with the number of matching datasets.
 */
final readonly class FacetBucket
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public ?string $id,
        public ?string $title,
        public int $count,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::string($data['id'] ?? $data['key'] ?? null),
            title: Values::text($data['title'] ?? $data['key_as_string'] ?? null),
            count: Values::int($data['doc_count'] ?? null) ?? 0,
            raw: $data,
        );
    }

    public function label(): string
    {
        return $this->title ?? $this->id ?? '';
    }
}
