<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Row of a tabular resource (/resources/{id}/data).
 *
 * Every cell carries a typed value ("val") and its textual representation
 * ("repr"). Cells are keyed by the technical column name ("col1", "col2", ...);
 * the header labels of the source file are kept in $headers.
 */
final readonly class TabularRow
{
    /**
     * @param array<string, mixed> $values Typed values keyed by column name.
     * @param array<string, string|null> $display Textual representations keyed by column name.
     * @param array<string, string> $headers Header labels keyed by column name.
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public string $id,
        public ?int $rowNumber,
        public int $resourceId,
        public array $values,
        public array $display,
        public array $headers,
        public ?CarbonImmutable $updatedAt,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource JSON:API resource object of type "row".
     * @param array<string, string> $headers
     */
    public static function fromResource(array $resource, int $resourceId, array $headers = []): self
    {
        $values = [];
        $display = [];

        foreach (Values::map($resource['attributes'] ?? null) as $column => $cell) {
            $cell = Values::map($cell);
            $values[$column] = $cell['val'] ?? null;
            $display[$column] = is_scalar($cell['repr'] ?? null) ? (string) $cell['repr'] : null;
        }

        uksort($values, self::compareColumns(...));
        uksort($display, self::compareColumns(...));
        $meta = Values::map($resource['meta'] ?? null);

        return new self(
            id: Values::string($resource['id'] ?? null) ?? '',
            rowNumber: Values::int($meta['row_no'] ?? null),
            resourceId: $resourceId,
            values: $values,
            display: $display,
            headers: $headers,
            updatedAt: Values::date($meta['updated_at'] ?? null),
            raw: $resource,
        );
    }

    /**
     * Typed value of a cell, addressed by column name ("col3") or header label ("Nazwisko").
     */
    public function get(string $column): mixed
    {
        return $this->values[$this->column($column)] ?? null;
    }

    /**
     * Textual representation of a cell, as shown on the portal.
     */
    public function text(string $column): ?string
    {
        return $this->display[$this->column($column)] ?? null;
    }

    /**
     * Values keyed by header label (column name when no label is known).
     *
     * @return array<string, mixed>
     */
    public function toNamedArray(): array
    {
        $named = [];

        foreach ($this->values as $column => $value) {
            $named[$this->headers[$column] ?? $column] = $value;
        }

        return $named;
    }

    private function column(string $column): string
    {
        if (array_key_exists($column, $this->values)) {
            return $column;
        }

        $found = array_search($column, $this->headers, true);

        return is_string($found) ? $found : $column;
    }

    private static function compareColumns(string $a, string $b): int
    {
        return strnatcmp($a, $b);
    }
}
