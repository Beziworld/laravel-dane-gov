<?php

declare(strict_types=1);

namespace Beziworld\DaneGov;

use Beziworld\DaneGov\Data\Dataset;
use Beziworld\DaneGov\Data\DownloadedFile;
use Beziworld\DaneGov\Data\Institution;
use Beziworld\DaneGov\Data\Page;
use Beziworld\DaneGov\Data\Resource;
use Beziworld\DaneGov\Data\SearchResult;
use Beziworld\DaneGov\Data\Showcase;
use Beziworld\DaneGov\Data\TabularRow;
use Beziworld\DaneGov\Exceptions\ApiException;
use Beziworld\DaneGov\Exceptions\DownloadFailed;
use Beziworld\DaneGov\Exceptions\NotFound;
use Beziworld\DaneGov\Exceptions\ResultWindowExceeded;
use Beziworld\DaneGov\Support\Values;
use Closure;
use GuzzleHttp\Psr7\Query as Psr7Query;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use JsonException;

/**
 * Client of the dane.gov.pl open data API (JSON:API, version 1.4 by default).
 *
 * Every call is a read-only GET. Lists are returned as lazy collections that
 * fetch further pages only while they are iterated; Page objects expose the
 * total count and the facets of one page. JSON responses are cached.
 */
class DaneGovClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private DaneGovConfig $config = new DaneGovConfig(),
        private readonly ?Cache $cache = null,
    ) {}

    public function config(): DaneGovConfig
    {
        return $this->config;
    }

    /**
     * Copy of the client sending another Accept-Language ("pl" or "en").
     */
    public function inLanguage(string $language): static
    {
        $clone = clone $this;
        $clone->config = $this->config->withLanguage($language);

        return $clone;
    }

    // Datasets

    /**
     * Datasets matching a phrase or a Query (filters, sorting), fetched lazily.
     *
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Dataset>
     */
    public function datasets(Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('datasets', Query::from($query), Dataset::fromResource(...));
    }

    /**
     * One page of datasets with the total count and the requested facets.
     *
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return Page<Dataset>
     */
    public function datasetsPage(Query|string|array|null $query = null, int $page = 1): Page
    {
        return $this->page('datasets', Query::from($query), $page, Dataset::fromResource(...));
    }

    public function dataset(int $id): Dataset
    {
        return Dataset::fromResource($this->document('datasets/' . $id));
    }

    /**
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Resource>
     */
    public function datasetResources(int $datasetId, Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('datasets/' . $datasetId . '/resources', Query::from($query), Resource::fromResource(...));
    }

    /**
     * @return LazyCollection<int, Showcase>
     */
    public function datasetShowcases(int $datasetId): LazyCollection
    {
        return $this->paginate('datasets/' . $datasetId . '/showcases', Query::make(), Showcase::fromResource(...));
    }

    // Resources

    /**
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Resource>
     */
    public function resources(Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('resources', Query::from($query), Resource::fromResource(...));
    }

    /**
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return Page<Resource>
     */
    public function resourcesPage(Query|string|array|null $query = null, int $page = 1): Page
    {
        return $this->page('resources', Query::from($query), $page, Resource::fromResource(...));
    }

    public function resource(int $id): Resource
    {
        return Resource::fromResource($this->document('resources/' . $id));
    }

    /**
     * Reader of the rows of a tabular resource (CSV, XLSX and similar files imported by the portal).
     */
    public function table(int $resourceId): TabularReader
    {
        return new TabularReader($this, $resourceId);
    }

    /**
     * Single row of a tabular resource by its identifier.
     */
    public function row(int $resourceId, string $rowId): TabularRow
    {
        if (preg_match('/^[A-Za-z0-9-]+$/', $rowId) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a row identifier.', $rowId));
        }

        $document = $this->document(sprintf('resources/%d/data/%s', $resourceId, $rowId));

        return TabularRow::fromResource(Values::map($document), $resourceId);
    }

    /**
     * Streams the rows of a tabular resource. Used by TabularReader.
     *
     * @internal
     * @param array<string, scalar|list<scalar>> $params
     * @return LazyCollection<int, TabularRow>
     */
    public function tabularRows(int $resourceId, array $params, ?int $perPage = null, ?int $limit = null): LazyCollection
    {
        return $this->paginate(
            sprintf('resources/%d/data', $resourceId),
            Query::from($params),
            static fn(array $row, array $headers): TabularRow => TabularRow::fromResource($row, $resourceId, $headers),
            $perPage,
            $limit,
        );
    }

    /**
     * One page of rows of a tabular resource, with meta (schema, headers, aggregations).
     *
     * @internal
     * @param array<string, scalar|list<scalar>> $params
     * @return Page<TabularRow>
     */
    public function tabularPage(int $resourceId, array $params, int $page = 1, ?int $perPage = null): Page
    {
        $query = Query::from($params);

        if ($perPage !== null) {
            $query = $query->perPage($perPage);
        }

        $document = $this->list(sprintf('resources/%d/data', $resourceId), $query, $page);
        $headers = self::headers($document);
        $items = array_map(static fn(array $row): TabularRow => TabularRow::fromResource($row, $resourceId, $headers), self::resourceObjects($document));

        return Page::fromDocument($document, $items, $page, self::perPageOf($query, $this->config));
    }

    /**
     * Streams a file of the resource to disk and returns where it was written.
     *
     * $path may be a file path or an existing directory (the file name is taken from the
     * file URL). $format selects one of the published files ("csv", "xlsx", "jsonld", ...);
     * by default the original file is downloaded.
     */
    public function download(int|Resource $resource, ?string $path = null, ?string $format = null): DownloadedFile
    {
        $resource = $resource instanceof Resource ? $resource : $this->resource($resource);
        [$url, $chosenFormat] = self::downloadSource($resource, $format);
        $target = $this->targetPath($resource, $path, $url, $chosenFormat);
        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw DownloadFailed::filesystem($target);
        }

        $temporary = $target . '.part';

        try {
            $response = $this->http->createPendingRequest()
                ->timeout($this->config->downloadTimeout)
                ->withHeaders(['X-API-VERSION' => $this->config->apiVersion])
                ->withOptions(['sink' => $temporary, 'allow_redirects' => ['max' => 5, 'protocols' => ['http', 'https']]])
                ->get($url);
        } catch (ConnectionException $exception) {
            @unlink($temporary);

            throw DownloadFailed::transport($url, $exception);
        }

        if ($response->failed()) {
            @unlink($temporary);

            throw DownloadFailed::http($url, $response->status());
        }

        if (! is_file($temporary) && file_put_contents($temporary, $response->body()) === false) {
            throw DownloadFailed::filesystem($target);
        }

        if (! @rename($temporary, $target)) {
            @unlink($temporary);

            throw DownloadFailed::filesystem($target);
        }

        return new DownloadedFile(
            path: $target,
            bytes: (int) filesize($target),
            contentType: Values::string($response->header('Content-Type')),
            format: $chosenFormat,
            url: $url,
        );
    }

    // Institutions

    /**
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Institution>
     */
    public function institutions(Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('institutions', Query::from($query), Institution::fromResource(...));
    }

    public function institution(int $id): Institution
    {
        return Institution::fromResource($this->document('institutions/' . $id));
    }

    /**
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Dataset>
     */
    public function institutionDatasets(int $institutionId, Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('institutions/' . $institutionId . '/datasets', Query::from($query), Dataset::fromResource(...));
    }

    // Showcases

    /**
     * Showcases (applications, websites and analyses built on open data).
     *
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, Showcase>
     */
    public function showcases(Query|string|array|null $query = null): LazyCollection
    {
        return $this->paginate('showcases', Query::from($query), Showcase::fromResource(...));
    }

    public function showcase(int $id): Showcase
    {
        return Showcase::fromResource($this->document('showcases/' . $id));
    }

    // Search

    /**
     * Search across datasets, resources, institutions, showcases and articles.
     * Narrow it with Query::make()->search('...')->where('model', ['dataset', 'resource']).
     *
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return LazyCollection<int, SearchResult>
     */
    public function search(Query|string|array|null $query): LazyCollection
    {
        return $this->paginate('search', Query::from($query), SearchResult::fromResource(...));
    }

    /**
     * One page of search results with counters per model in the aggregations.
     *
     * @param Query|string|array<string, scalar|list<scalar>>|null $query
     * @return Page<SearchResult>
     */
    public function searchPage(Query|string|array|null $query, int $page = 1): Page
    {
        return $this->page('search', Query::from($query), $page, SearchResult::fromResource(...));
    }

    // Low level

    /**
     * Decoded JSON:API document of any path below the API version, for example "dga-aggregated".
     *
     * @param array<string, scalar|list<scalar>> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request(trim($path, '/'), $query);
    }

    /**
     * @template T
     * @param Closure(array<string, mixed>): T $map
     * @return Page<T>
     */
    private function page(string $path, Query $query, int $page, Closure $map): Page
    {
        $document = $this->list($path, $query, $page);

        return Page::fromDocument($document, array_map($map, self::resourceObjects($document)), $page, self::perPageOf($query, $this->config));
    }

    /**
     * Lazily walks the pages of a list endpoint (pages start at 1). The mapper receives the
     * resource object and the column labels of the page (tabular data only).
     *
     * @template T
     * @param Closure(array<string, mixed>, array<string, string>): T $map
     * @return LazyCollection<int, T>
     */
    private function paginate(string $path, Query $query, Closure $map, ?int $perPage = null, ?int $limit = null): LazyCollection
    {
        if ($perPage !== null) {
            $query = $query->perPage($perPage);
        }

        $size = self::perPageOf($query, $this->config);
        $window = DaneGovConfig::MAX_RESULT_WINDOW;

        return LazyCollection::make(function () use ($path, $query, $map, $size, $window, $limit) {
            $page = 1;
            $yielded = 0;

            while (true) {
                $document = $this->list($path, $query, $page);
                $headers = self::headers($document);
                $rows = self::resourceObjects($document);

                foreach ($rows as $row) {
                    yield $map($row, $headers);
                    $yielded++;
                }

                $links = Values::map($document['links'] ?? null);

                if ($rows === [] || ! isset($links['next']) || ($limit !== null && $yielded >= $limit)) {
                    return;
                }

                if (($page + 1) * $size > $window) {
                    $meta = Values::map($document['meta'] ?? null);

                    throw ResultWindowExceeded::for($path, Values::int($meta['count'] ?? null) ?? 0, $window);
                }

                $page++;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function list(string $path, Query $query, int $page): array
    {
        $params = $query->toArray();
        $params['per_page'] ??= $this->config->perPage;
        $params['page'] = max(1, $page);

        return $this->request($path, $params);
    }

    /**
     * Primary data of a single-object document.
     *
     * @return array<string, mixed>
     */
    private function document(string $path): array
    {
        $document = $this->request($path, []);
        $data = $document['data'] ?? null;

        return is_array($data) && ! array_is_list($data) ? Values::map($data) : throw ApiException::unexpectedResponse($path, 'no data object');
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        ksort($query);
        $queryString = Psr7Query::build($query);
        $url = sprintf('%s/%s/%s%s', $this->config->baseUrl, $this->config->apiVersion, $path, $queryString !== '' ? '?' . $queryString : '');
        $cacheKey = sprintf('%s:%s:%s', $this->config->cachePrefix, $this->config->language, sha1($url));
        $useCache = $this->cache !== null && $this->config->cachesResponses();

        if ($useCache) {
            $cached = $this->cache->get($cacheKey);

            if (is_array($cached)) {
                return Values::map($cached);
            }
        }

        $response = $this->send($url, $path);

        if ($response->status() === 404) {
            throw NotFound::fromResponse(404, $path, self::decode($response), $response->body());
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($response->status(), $path, self::decode($response), $response->body());
        }

        $document = self::decode($response) ?? throw ApiException::unexpectedResponse($path, 'the body is not valid JSON');
        $document = Values::map($document);

        if ($useCache) {
            $this->cache->put($cacheKey, $document, $this->config->cacheTtl);
        }

        return $document;
    }

    private function send(string $url, string $path): Response
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->http->createPendingRequest()
                    ->timeout($this->config->timeout)
                    ->accept('application/vnd.api+json')
                    ->withHeaders([
                        'X-API-VERSION' => $this->config->apiVersion,
                        'Accept-Language' => $this->config->language,
                    ])
                    ->get($url);
            } catch (ConnectionException $exception) {
                if ($attempt++ < $this->config->retries) {
                    Sleep::usleep(250_000 * $attempt);

                    continue;
                }

                throw ApiException::transport($path, $exception);
            }

            if ($response->serverError() && $attempt++ < $this->config->retries) {
                Sleep::usleep(250_000 * $attempt);

                continue;
            }

            return $response;
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function downloadSource(Resource $resource, ?string $format): array
    {
        if ($format !== null) {
            $format = strtolower(trim($format));
            $file = $resource->file($format);
            $url = $file->downloadUrl ?? match (true) {
                $format === 'csv' => $resource->csvDownloadUrl,
                $format === 'jsonld' => $resource->jsonldDownloadUrl,
                $format === $resource->format => $resource->downloadUrl,
                default => null,
            };

            if ($url === null) {
                throw DownloadFailed::formatUnavailable($resource->id, $format, $resource->availableFormats());
            }
        } else {
            $url = $resource->downloadUrl ?? $resource->fileUrl ?? $resource->link ?? throw DownloadFailed::noFile($resource->id);
            $format = $resource->format;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw DownloadFailed::unsafeUrl($url);
        }

        return [$url, $format];
    }

    private function targetPath(Resource $resource, ?string $path, string $url, ?string $format): string
    {
        $directory = $this->config->downloadDirectory ?? (function_exists('storage_path') ? storage_path('app/dane-gov') : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dane-gov');

        if ($path === null || $path === '' || is_dir($path) || str_ends_with($path, '/') || str_ends_with($path, '\\')) {
            $directory = $path !== null && $path !== '' ? rtrim($path, '/\\') : $directory;

            return $directory . DIRECTORY_SEPARATOR . self::fileName($resource, $url, $format);
        }

        return str_contains($path, '/') || str_contains($path, '\\') ? $path : $directory . DIRECTORY_SEPARATOR . $path;
    }

    private static function fileName(Resource $resource, string $url, ?string $format): string
    {
        $candidate = $resource->fileUrl !== null && ($format === null || $format === $resource->format) ? $resource->fileUrl : $url;
        $name = rawurldecode(basename((string) parse_url($candidate, PHP_URL_PATH)));
        $name = (string) preg_replace('/[^\pL\pN._-]+/u', '_', $name);

        if ($name === '' || in_array($name, ['file', 'csv', 'jsonld', 'xlsx'], true) || ! str_contains($name, '.')) {
            $name = sprintf('resource-%d.%s', $resource->id, $format ?? 'bin');
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $document
     * @return list<array<string, mixed>>
     */
    private static function resourceObjects(array $document): array
    {
        $data = $document['data'] ?? [];

        return is_array($data) && array_is_list($data) ? array_values(array_map(Values::map(...), array_filter($data, is_array(...)))) : [];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, string>
     */
    private static function headers(array $document): array
    {
        $headers = [];

        foreach (Values::map(Values::map($document['meta'] ?? null)['headers_map'] ?? null) as $column => $label) {
            if (is_string($label)) {
                $headers[$column] = $label;
            }
        }

        return $headers;
    }

    private static function perPageOf(Query $query, DaneGovConfig $config): int
    {
        $perPage = $query->get('per_page');

        return is_numeric($perPage) ? max(1, (int) $perPage) : $config->perPage;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decode(Response $response): ?array
    {
        try {
            $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
