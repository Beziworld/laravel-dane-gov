<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

/**
 * Column of a tabular resource: technical name ("col1"), header label and Table Schema type.
 */
final readonly class Column
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $type,
        public ?string $format,
    ) {}
}
