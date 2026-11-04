<?php

declare(strict_types=1);

namespace Beziworld\DaneGov;

use InvalidArgumentException;

/**
 * Immutable builder of list parameters: full-text query, field filters, sorting, facets and includes.
 *
 * Field filters follow the API syntax "field[lookup]=value", with nested fields
 * written in dot notation: where('institution.id', 34) sends
 * "institution[id][term]=34", where('openness_score', 3, 'gte') sends
 * "openness_score[gte]=3" and an array value uses the "terms" lookup.
 */
final class Query
{
    /**
     * Lookups accepted by the API for filters and text fields.
     */
    public const array LOOKUPS = ['term', 'terms', 'gt', 'gte', 'lt', 'lte', 'startswith', 'endswith', 'contains', 'match', 'phrase', 'prefix', 'query', 'suggest'];

    /**
     * @param array<string, scalar|list<scalar>> $params
     */
    private function __construct(private readonly array $params = []) {}

    public static function make(): self
    {
        return new self();
    }

    /**
     * Builds a query from a phrase, a Query or an array of raw parameters.
     *
     * @param self|string|array<string, scalar|list<scalar>>|null $value
     */
    public static function from(self|string|array|null $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            is_string($value) => trim($value) === '' ? new self() : (new self())->search($value),
            is_array($value) => new self($value),
            default => new self(),
        };
    }

    /**
     * Full-text search ("q").
     */
    public function search(string $phrase): self
    {
        return $this->with('q', trim($phrase));
    }

    /**
     * Filter on a field; an array value uses the "terms" lookup.
     *
     * @param scalar|list<scalar> $value
     */
    public function where(string $field, string|int|float|bool|array $value, ?string $lookup = null): self
    {
        $lookup ??= is_array($value) ? 'terms' : 'term';

        if (! in_array($lookup, self::LOOKUPS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown lookup "%s"; use one of: %s.', $lookup, implode(', ', self::LOOKUPS)));
        }

        $segments = array_filter(explode('.', trim($field)), static fn(string $segment): bool => $segment !== '');

        if ($segments === [] || preg_match('/^[a-z0-9_]+$/', implode('', $segments)) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid field name.', $field));
        }

        $key = array_shift($segments) . implode('', array_map(static fn(string $segment): string => '[' . $segment . ']', $segments)) . '[' . $lookup . ']';

        return $this->with($key, is_array($value) ? implode(',', array_map(self::scalar(...), $value)) : self::scalar($value));
    }

    /**
     * Sort fields; a leading minus sorts in descending order ("-modified").
     */
    public function sort(string ...$fields): self
    {
        $fields = array_values(array_filter(array_map(trim(...), $fields), static fn(string $field): bool => $field !== ''));

        return $this->with('sort', count($fields) === 1 ? $fields[0] : implode(',', $fields));
    }

    /**
     * Facets (aggregations) returned in the meta of the answer, for example "by_institution", "by_format".
     */
    public function facets(string ...$names): self
    {
        return $this->with('facet[terms]', implode(',', array_map(trim(...), $names)));
    }

    /**
     * Related objects returned in the "included" section, for example "institution".
     */
    public function include(string ...$relations): self
    {
        return $this->with('include', implode(',', array_map(trim(...), $relations)));
    }

    public function perPage(int $perPage): self
    {
        if ($perPage < 1 || $perPage > DaneGovConfig::MAX_PER_PAGE) {
            throw new InvalidArgumentException('The page size must be between 1 and 100.');
        }

        return $this->with('per_page', $perPage);
    }

    /**
     * Any other parameter, sent as is.
     *
     * @param scalar|list<scalar> $value
     */
    public function with(string $key, string|int|float|bool|array $value): self
    {
        $params = $this->params;
        $params[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;

        return new self($params);
    }

    public function without(string $key): self
    {
        $params = $this->params;
        unset($params[$key]);

        return new self($params);
    }

    public function get(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    public function toArray(): array
    {
        return $this->params;
    }

    private static function scalar(string|int|float|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
