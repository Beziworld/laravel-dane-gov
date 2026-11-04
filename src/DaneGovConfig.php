<?php

declare(strict_types=1);

namespace Beziworld\DaneGov;

use Beziworld\DaneGov\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable view of the "dane-gov" configuration file.
 */
final readonly class DaneGovConfig
{
    public const string DEFAULT_URL = 'https://api.dane.gov.pl';

    public const string DEFAULT_VERSION = '1.4';

    /**
     * Largest page accepted by the API.
     */
    public const int MAX_PER_PAGE = 100;

    /**
     * Deepest record reachable by paging (page x per_page); deeper pages fail with HTTP 400.
     */
    public const int MAX_RESULT_WINDOW = 10000;

    public function __construct(
        public string $baseUrl = self::DEFAULT_URL,
        public string $apiVersion = self::DEFAULT_VERSION,
        public string $language = 'pl',
        public int $perPage = self::MAX_PER_PAGE,
        public int $timeout = 30,
        public int $retries = 2,
        public bool $cacheEnabled = true,
        public ?string $cacheStore = null,
        public int $cacheTtl = 3600,
        public string $cachePrefix = 'dane-gov',
        public int $downloadTimeout = 600,
        public ?string $downloadDirectory = null,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $string = static fn(mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        $integer = static fn(mixed $value, int $default, int $min): int => is_numeric($value) && (int) $value >= $min ? (int) $value : $default;

        $baseUrl = rtrim($string($config['base_url'] ?? null) ?? self::DEFAULT_URL, '/');

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw InvalidConfiguration::invalid('base_url', 'DANE_GOV_URL', 'not a valid URL');
        }

        $version = $string($config['api_version'] ?? null) ?? self::DEFAULT_VERSION;

        if (preg_match('/^\d+\.\d+$/', $version) !== 1) {
            throw InvalidConfiguration::invalid('api_version', 'DANE_GOV_API_VERSION', 'expected a version such as "1.4"');
        }

        $language = strtolower($string($config['language'] ?? null) ?? 'pl');

        if (! in_array($language, ['pl', 'en'], true)) {
            throw InvalidConfiguration::invalid('language', 'DANE_GOV_LANG', 'expected "pl" or "en"');
        }

        $perPage = $integer($config['per_page'] ?? null, self::MAX_PER_PAGE, 1);

        if ($perPage > self::MAX_PER_PAGE) {
            throw InvalidConfiguration::invalid('per_page', 'DANE_GOV_PER_PAGE', 'the API accepts at most 100 records per page');
        }

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $download = is_array($config['download'] ?? null) ? $config['download'] : [];

        return new self(
            baseUrl: $baseUrl,
            apiVersion: $version,
            language: $language,
            perPage: $perPage,
            timeout: $integer($config['timeout'] ?? null, 30, 1),
            retries: $integer($config['retries'] ?? null, 2, 0),
            cacheEnabled: self::flag($cache['enabled'] ?? true),
            cacheStore: $string($cache['store'] ?? null),
            cacheTtl: $integer($cache['ttl'] ?? null, 3600, 0),
            cachePrefix: $string($cache['prefix'] ?? null) ?? 'dane-gov',
            downloadTimeout: $integer($download['timeout'] ?? null, 600, 1),
            downloadDirectory: $string($download['directory'] ?? null),
        );
    }

    /**
     * Copy using another language for Accept-Language.
     */
    public function withLanguage(string $language): self
    {
        $language = strtolower(trim($language));

        if (! in_array($language, ['pl', 'en'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported language "%s"; use "pl" or "en".', $language));
        }

        $values = get_object_vars($this);
        $values['language'] = $language;

        return new self(...$values);
    }

    public function cachesResponses(): bool
    {
        return $this->cacheEnabled && $this->cacheTtl > 0;
    }

    private static function flag(mixed $value): bool
    {
        if (is_string($value)) {
            return ! in_array(strtolower(trim($value)), ['0', 'false', 'off', 'no', ''], true);
        }

        return (bool) $value;
    }
}
