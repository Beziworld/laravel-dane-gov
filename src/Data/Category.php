<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;

/**
 * Category of a dataset: the portal category or a DCAT-AP theme such as "SOCI".
 */
final readonly class Category
{
    public function __construct(
        public ?int $id,
        public ?string $code,
        public string $title,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Values::int($data['id'] ?? null),
            code: Values::string($data['code'] ?? null),
            title: Values::text($data['title'] ?? null) ?? '',
        );
    }
}
