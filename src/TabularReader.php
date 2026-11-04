<?php

declare(strict_types=1);

namespace Beziworld\DaneGov;

use Beziworld\DaneGov\Data\Column;
use Beziworld\DaneGov\Data\TabularRow;
use Beziworld\DaneGov\Support\Values;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Streams the rows of a tabular resource with filters, sorting and aggregations.
 *
 * Conditions are combined with AND into the "q" parameter, which uses the
 * query string syntax of the API (field:value, ranges, wildcards, boolean
 * operators). Rows are fetched page by page while they are iterated.
 *
 * @implements IteratorAggregate<int, TabularRow>
 */
final class TabularReader implements IteratorAggregate
{
    /**
     * @param list<string> $conditions
     * @param list<string> $sort
     * @param list<string> $sum
     * @param list<string> $avg
     */
    public function __construct(
        private readonly DaneGovClient $client,
        public readonly int $resourceId,
        private readonly array $conditions = [],
        private readonly array $sort = [],
        private readonly array $sum = [],
        private readonly array $avg = [],
        private readonly ?int $perPage = null,
        private readonly ?int $limit = null,
    ) {}

    /**
     * Rows whose column equals the value. Text is escaped and quoted when it contains spaces.
     */
    public function where(string $column, string|int|float|bool $value): self
    {
        return $this->query(self::column($column) . ':' . self::term($value));
    }

    /**
     * Rows whose column matches any of the values.
     *
     * @param list<string|int|float> $values
     */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('whereIn() needs at least one value.');
        }

        $column = self::column($column);

        return $this->query('(' . implode(' OR ', array_map(static fn(string|int|float $value): string => $column . ':' . self::term($value), $values)) . ')');
    }

    /**
     * Rows whose column lies in the inclusive range; null leaves a side open.
     */
    public function whereBetween(string $column, string|int|float|null $from, string|int|float|null $to): self
    {
        return $this->query(sprintf('%s:[%s TO %s]', self::column($column), $from === null ? '*' : self::term($from), $to === null ? '*' : self::term($to)));
    }

    /**
     * Raw condition in the query string syntax, for example "col3:Now*" or "(col1:A OR col1:B)".
     */
    public function query(string $condition): self
    {
        $condition = trim($condition);

        return $condition === '' ? $this : $this->copy(conditions: [...$this->conditions, $condition]);
    }

    /**
     * Sort columns; a leading minus sorts in descending order ("-col4").
     */
    public function sort(string ...$columns): self
    {
        return $this->copy(sort: [...$this->sort, ...array_values(array_map(trim(...), $columns))]);
    }

    /**
     * Columns to sum over all matching rows (see aggregations()).
     */
    public function sum(string ...$columns): self
    {
        return $this->copy(sum: [...$this->sum, ...array_values(array_map(self::column(...), $columns))]);
    }

    /**
     * Columns to average over all matching rows (see aggregations()).
     */
    public function avg(string ...$columns): self
    {
        return $this->copy(avg: [...$this->avg, ...array_values(array_map(self::column(...), $columns))]);
    }

    public function perPage(int $perPage): self
    {
        if ($perPage < 1 || $perPage > DaneGovConfig::MAX_PER_PAGE) {
            throw new InvalidArgumentException('The page size must be between 1 and 100.');
        }

        return $this->copy(perPage: $perPage);
    }

    /**
     * Stops after the given number of rows (and therefore never runs into the result window).
     */
    public function limit(int $limit): self
    {
        return $this->copy(limit: max(0, $limit));
    }

    /**
     * Combined "q" parameter, or null without conditions.
     */
    public function toQueryString(): ?string
    {
        if ($this->conditions === []) {
            return null;
        }

        return count($this->conditions) === 1 ? $this->conditions[0] : implode(' AND ', array_map(static fn(string $condition): string => '(' . $condition . ')', $this->conditions));
    }

    /**
     * @return LazyCollection<int, TabularRow>
     */
    public function rows(): LazyCollection
    {
        $rows = $this->client->tabularRows($this->resourceId, $this->params(), $this->perPage, $this->limit);

        return $this->limit !== null ? $rows->take($this->limit) : $rows;
    }

    public function first(): ?TabularRow
    {
        return $this->client->tabularPage($this->resourceId, $this->params(), 1, 1)->items[0] ?? null;
    }

    /**
     * Number of rows matching the conditions (one request for a single row).
     */
    public function count(): int
    {
        return $this->client->tabularPage($this->resourceId, $this->params(), 1, 1)->total;
    }

    /**
     * Columns of the resource with labels and types.
     *
     * @return list<Column>
     */
    public function columns(): array
    {
        $meta = $this->client->tabularPage($this->resourceId, $this->params(), 1, 1)->meta;
        $labels = Values::map($meta['headers_map'] ?? null);
        $schema = Values::map($meta['data_schema'] ?? null);
        $columns = [];

        foreach (is_array($schema['fields'] ?? null) ? $schema['fields'] : [] as $field) {
            $field = Values::map($field);
            $name = Values::string($field['name'] ?? null);

            if ($name !== null) {
                $columns[] = new Column($name, Values::string($labels[$name] ?? null) ?? $name, Values::string($field['type'] ?? null), Values::string($field['format'] ?? null));
            }
        }

        if ($columns === []) {
            foreach ($labels as $name => $label) {
                $columns[] = new Column($name, Values::string($label) ?? $name, null, null);
            }
        }

        return $columns;
    }

    /**
     * Sums and averages requested with sum() and avg(), keyed by kind and column.
     *
     * @return array{sum: array<string, float>, avg: array<string, float>}
     */
    public function aggregations(): array
    {
        $aggregations = $this->client->tabularPage($this->resourceId, $this->params(), 1, 1)->aggregations;
        $result = ['sum' => [], 'avg' => []];

        foreach (['sum', 'avg'] as $kind) {
            foreach (is_array($aggregations[$kind] ?? null) ? $aggregations[$kind] : [] as $item) {
                $item = Values::map($item);
                $column = Values::string($item['column'] ?? null);

                if ($column !== null && is_numeric($item['value'] ?? null)) {
                    $result[$kind][$column] = (float) $item['value'];
                }
            }
        }

        return $result;
    }

    public function getIterator(): Traversable
    {
        return $this->rows()->getIterator();
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    public function params(): array
    {
        return array_filter([
            'q' => $this->toQueryString(),
            'sort' => $this->sort === [] ? null : implode(',', $this->sort),
            'sum' => $this->sum === [] ? null : implode(',', $this->sum),
            'avg' => $this->avg === [] ? null : implode(',', $this->avg),
        ], static fn(?string $value): bool => $value !== null);
    }

    /**
     * Escapes a value for the query string syntax.
     */
    public static function term(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (preg_match('/\s/u', $value) === 1) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }

        return (string) preg_replace('/([+\-=&|><!(){}\[\]^"~*?:\\\\\/])/', '\\\\$1', $value);
    }

    /**
     * @param list<string>|null $conditions
     * @param list<string>|null $sort
     * @param list<string>|null $sum
     * @param list<string>|null $avg
     */
    private function copy(?array $conditions = null, ?array $sort = null, ?array $sum = null, ?array $avg = null, ?int $perPage = null, ?int $limit = null): self
    {
        return new self(
            $this->client,
            $this->resourceId,
            $conditions ?? $this->conditions,
            $sort ?? $this->sort,
            $sum ?? $this->sum,
            $avg ?? $this->avg,
            $perPage ?? $this->perPage,
            $limit ?? $this->limit,
        );
    }

    private static function column(string $column): string
    {
        $column = trim($column);

        if (preg_match('/^[A-Za-z0-9_.]+$/', $column) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a column name; use the technical name such as "col1".', $column));
        }

        return $column;
    }
}
