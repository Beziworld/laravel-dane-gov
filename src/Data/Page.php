<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;

/**
 * One page of a list endpoint with its total count, facets and links.
 *
 * @template TItem
 */
final readonly class Page
{
    /**
     * @param list<TItem> $items
     * @param array<string, list<FacetBucket>> $facets Buckets keyed by facet name, for example "by_institution".
     * @param array<string, mixed> $aggregations All aggregations as returned (for example "counters" of /search, "sum" and "avg" of tabular data).
     * @param array<string, string> $links
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
        public array $aggregations,
        public array $links,
        public array $meta,
    ) {}

    /**
     * @template T
     * @param array<string, mixed> $document
     * @param list<T> $items
     * @return self<T>
     */
    public static function fromDocument(array $document, array $items, int $page, int $perPage): self
    {
        $meta = Values::map($document['meta'] ?? null);
        $facets = [];
        $aggregations = [];

        foreach (Values::map($meta['aggregations'] ?? null) as $name => $value) {
            $aggregations[$name] = $value;

            if (is_array($value) && array_is_list($value) && str_starts_with($name, 'by_')) {
                $facets[$name] = array_values(array_map(
                    static fn(mixed $bucket): FacetBucket => FacetBucket::fromArray(Values::map($bucket)),
                    array_filter($value, is_array(...)),
                ));
            }
        }

        $links = [];

        foreach (Values::map($document['links'] ?? null) as $name => $link) {
            if (is_string($link)) {
                $links[$name] = $link;
            }
        }

        return new self(
            items: $items,
            total: Values::int($meta['count'] ?? null) ?? count($items),
            page: $page,
            perPage: $perPage,
            facets: $facets,
            aggregations: $aggregations,
            links: $links,
            meta: $meta,
        );
    }

    public function hasMorePages(): bool
    {
        return isset($this->links['next']);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    /**
     * @return list<FacetBucket>
     */
    public function facet(string $name): array
    {
        return $this->facets[$name] ?? [];
    }
}
