<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Exceptions;

/**
 * Raised when iteration would go beyond the first 10 000 records of a result.
 *
 * The API pages through a search index that refuses deeper pages (HTTP 400
 * "all shards failed"). Narrow the query with filters, or download the file
 * of the resource to read every row.
 */
final class ResultWindowExceeded extends DaneGovException
{
    public function __construct(
        string $message,
        public readonly int $total,
        public readonly int $window,
    ) {
        parent::__construct($message);
    }

    public static function for(string $path, int $total, int $window): self
    {
        return new self(
            sprintf('The result of "%s" has %d records, but the API pages through the first %d only. Add filters or download the file instead.', $path, $total, $window),
            $total,
            $window,
        );
    }
}
