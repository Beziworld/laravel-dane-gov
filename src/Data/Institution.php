<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Data;

use Beziworld\DaneGov\Support\Values;
use Carbon\CarbonImmutable;

/**
 * Institution (dostawca): the public body or organisation that publishes datasets.
 */
final readonly class Institution
{
    /**
     * @param array<string, mixed> $raw The JSON:API resource object.
     */
    public function __construct(
        public int $id,
        public ?string $slug,
        public string $title,
        public ?string $abbreviation,
        public ?string $type,
        public ?string $regon,
        public ?string $street,
        public ?string $streetType,
        public ?string $streetNumber,
        public ?string $flatNumber,
        public ?string $postalCode,
        public ?string $city,
        public ?string $email,
        public ?string $phone,
        public ?string $fax,
        public ?string $website,
        public ?string $epuap,
        public ?string $electronicDeliveryAddress,
        public ?int $datasetsCount,
        public ?int $resourcesCount,
        public ?CarbonImmutable $created,
        public ?CarbonImmutable $modified,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $resource JSON:API resource object of type "institution".
     */
    public static function fromResource(array $resource): self
    {
        $attributes = Values::map($resource['attributes'] ?? null);

        return new self(
            id: Values::int($resource['id'] ?? null) ?? 0,
            slug: Values::string($attributes['slug'] ?? null),
            title: Values::text($attributes['title'] ?? null) ?? '',
            abbreviation: Values::string($attributes['abbreviation'] ?? null),
            type: Values::string($attributes['institution_type'] ?? null),
            regon: Values::string($attributes['regon'] ?? null),
            street: Values::string($attributes['street'] ?? null),
            streetType: Values::string($attributes['street_type'] ?? null),
            streetNumber: Values::string($attributes['street_number'] ?? null),
            flatNumber: Values::string($attributes['flat_number'] ?? null),
            postalCode: Values::string($attributes['postal_code'] ?? null),
            city: Values::string($attributes['city'] ?? null),
            email: Values::string($attributes['email'] ?? null),
            phone: Values::string($attributes['tel'] ?? null),
            fax: Values::string($attributes['fax'] ?? null),
            website: Values::string($attributes['website'] ?? null),
            epuap: Values::string($attributes['epuap'] ?? null),
            electronicDeliveryAddress: Values::string($attributes['electronic_delivery_address'] ?? null),
            datasetsCount: Values::relatedCount($resource, 'datasets'),
            resourcesCount: Values::relatedCount($resource, 'resources'),
            created: Values::date($attributes['created'] ?? null),
            modified: Values::date($attributes['modified'] ?? null),
            raw: $resource,
        );
    }

    /**
     * Postal address in one line, for example "al. Niepodległości 208, 00-925 Warszawa".
     */
    public function address(): ?string
    {
        $street = trim(implode(' ', array_filter([$this->streetType, $this->street, $this->streetNumber], is_string(...))));

        if ($street !== '' && $this->flatNumber !== null) {
            $street .= '/' . $this->flatNumber;
        }

        $city = trim(implode(' ', array_filter([$this->postalCode, $this->city], is_string(...))));
        $address = implode(', ', array_filter([$street, $city], static fn(string $part): bool => $part !== ''));

        return $address !== '' ? $address : null;
    }
}
