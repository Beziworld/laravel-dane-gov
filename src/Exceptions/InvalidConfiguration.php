<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Exceptions;

/**
 * Raised when the package configuration is incomplete or invalid.
 */
final class InvalidConfiguration extends DaneGovException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('dane.gov.pl "%s" is not configured. Set %s in the environment.', $key, $env));
    }

    public static function invalid(string $key, string $env, string $reason): self
    {
        return new self(sprintf('dane.gov.pl "%s" is invalid (%s). Check %s in the environment.', $key, $reason, $env));
    }
}
