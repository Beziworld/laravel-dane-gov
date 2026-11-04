<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Facades;

use Beziworld\DaneGov\DaneGovClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Beziworld\DaneGov\DaneGovConfig config()
 * @method static \Beziworld\DaneGov\DaneGovClient inLanguage(string $language)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Dataset> datasets(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Beziworld\DaneGov\Data\Page<\Beziworld\DaneGov\Data\Dataset> datasetsPage(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null, int $page = 1)
 * @method static \Beziworld\DaneGov\Data\Dataset dataset(int $id)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Resource> datasetResources(int $datasetId, \Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Showcase> datasetShowcases(int $datasetId)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Resource> resources(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Beziworld\DaneGov\Data\Page<\Beziworld\DaneGov\Data\Resource> resourcesPage(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null, int $page = 1)
 * @method static \Beziworld\DaneGov\Data\Resource resource(int $id)
 * @method static \Beziworld\DaneGov\TabularReader table(int $resourceId)
 * @method static \Beziworld\DaneGov\Data\TabularRow row(int $resourceId, string $rowId)
 * @method static \Beziworld\DaneGov\Data\DownloadedFile download(int|\Beziworld\DaneGov\Data\Resource $resource, string|null $path = null, string|null $format = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Institution> institutions(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Beziworld\DaneGov\Data\Institution institution(int $id)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Dataset> institutionDatasets(int $institutionId, \Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\Showcase> showcases(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query = null)
 * @method static \Beziworld\DaneGov\Data\Showcase showcase(int $id)
 * @method static \Illuminate\Support\LazyCollection<int, \Beziworld\DaneGov\Data\SearchResult> search(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query)
 * @method static \Beziworld\DaneGov\Data\Page<\Beziworld\DaneGov\Data\SearchResult> searchPage(\Beziworld\DaneGov\Query|string|array<string, mixed>|null $query, int $page = 1)
 * @method static array<string, mixed> get(string $path, array<string, mixed> $query = [])
 *
 * @see DaneGovClient
 */
final class DaneGov extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DaneGovClient::class;
    }
}
