<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Exceptions;

use Throwable;

/**
 * Raised when a resource file cannot be downloaded or written to disk.
 */
final class DownloadFailed extends DaneGovException
{
    public static function noFile(int $resourceId): self
    {
        return new self(sprintf('Resource %d has no downloadable file.', $resourceId));
    }

    /**
     * @param list<string> $available
     */
    public static function formatUnavailable(int $resourceId, string $format, array $available): self
    {
        return new self(sprintf('Resource %d has no "%s" file. Available formats: %s.', $resourceId, $format, $available === [] ? 'none' : implode(', ', $available)));
    }

    public static function unsafeUrl(string $url): self
    {
        return new self(sprintf('Refusing to download from "%s": only http and https URLs are allowed.', $url));
    }

    public static function http(string $url, int $status): self
    {
        return new self(sprintf('Downloading "%s" failed with HTTP %d.', $url, $status), $status);
    }

    public static function transport(string $url, Throwable $previous): self
    {
        return new self(sprintf('Downloading "%s" failed: %s', $url, $previous->getMessage()), 0, $previous);
    }

    public static function filesystem(string $path): self
    {
        return new self(sprintf('Cannot write the downloaded file to "%s".', $path));
    }
}
