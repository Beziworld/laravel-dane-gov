<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Defensive readers for loosely typed JSON:API payloads.
 *
 * @internal
 */
final class Values
{
    public const string TIMEZONE = 'Europe/Warsaw';

    public static function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * Plain text of a field that may contain search highlighting ("<mark>") or HTML.
     */
    public static function text(mixed $value): ?string
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        $plain = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '' ? $plain : null;
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    public static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(self::string(...), $value), is_string(...)));
    }

    /**
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(intval(...), array_filter($value, is_numeric(...))));
    }

    /**
     * Timestamp in Polish local time; accepts ISO 8601 ("2018-02-12T14:05:25Z") and the
     * "2015-06-22 09:41:54.724473+00:00" form used by some endpoints.
     */
    public static function date(mixed $value): ?CarbonImmutable
    {
        $value = self::string($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->setTimezone(self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /**
     * Identifier of a to-one relationship of a JSON:API resource object.
     *
     * @param array<string, mixed> $resource
     */
    public static function relatedId(array $resource, string $relationship): ?int
    {
        $relationships = self::map($resource['relationships'] ?? null);
        $data = self::map(self::map($relationships[$relationship] ?? null)['data'] ?? null);

        return self::int($data['id'] ?? null);
    }

    /**
     * Count published in the meta of a to-many relationship.
     *
     * @param array<string, mixed> $resource
     */
    public static function relatedCount(array $resource, string $relationship): ?int
    {
        $relationships = self::map($resource['relationships'] ?? null);
        $meta = self::map(self::map($relationships[$relationship] ?? null)['meta'] ?? null);

        return self::int($meta['count'] ?? null);
    }
}
