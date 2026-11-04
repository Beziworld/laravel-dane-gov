<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Console;

use Beziworld\DaneGov\DaneGovClient;
use Beziworld\DaneGov\Data\Dataset;
use Beziworld\DaneGov\Data\FacetBucket;
use Beziworld\DaneGov\Data\Resource;
use Beziworld\DaneGov\Exceptions\DaneGovException;
use Beziworld\DaneGov\Query;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Searches datasets (or resources) of dane.gov.pl.
 */
final class SearchCommand extends Command
{
    protected $signature = 'dane:search
        {query : Phrase to search for}
        {--resources : Search resources instead of datasets}
        {--institution= : Limit to an institution id}
        {--format= : Limit to a file format, for example csv}
        {--sort= : Sort field, for example -modified}
        {--facets : Also show the institutions and formats of all matches}
        {--lang= : Language: pl or en}
        {--limit=20 : Maximum number of rows}';

    protected $description = 'Search datasets or resources on dane.gov.pl';

    public function handle(DaneGovClient $client): int
    {
        $phrase = $this->argument('query');
        $limit = max(1, min(100, (int) $this->option('limit')));
        $query = Query::make()->search(is_scalar($phrase) ? (string) $phrase : '')->perPage($limit);

        try {
            $lang = $this->option('lang');
            $client = is_string($lang) && $lang !== '' ? $client->inLanguage($lang) : $client;

            foreach (['institution' => 'institution.id', 'format' => 'format'] as $option => $field) {
                $value = $this->option($option);

                if (is_string($value) && $value !== '') {
                    $query = $query->where($field, $value);
                }
            }

            $sort = $this->option('sort');

            if (is_string($sort) && $sort !== '') {
                $query = $query->sort($sort);
            }

            if ($this->option('resources') === true) {
                $page = $client->resourcesPage($query);
                $this->table(['Id', 'Title', 'Format', 'Dataset', 'Tabular', 'Modified'], array_map(static fn(Resource $resource): array => [
                    $resource->id,
                    mb_strimwidth($resource->title, 0, 70, '...'),
                    $resource->format ?? '',
                    $resource->datasetId ?? '',
                    $resource->hasTabularData ? 'yes' : 'no',
                    $resource->modified?->toDateString() ?? '',
                ], $page->items));
            } else {
                $page = $client->datasetsPage($this->option('facets') === true ? $query->facets('by_institution', 'by_format') : $query);
                $this->table(['Id', 'Title', 'Institution', 'Formats', 'Modified'], array_map(static fn(Dataset $dataset): array => [
                    $dataset->id,
                    mb_strimwidth($dataset->title, 0, 70, '...'),
                    $dataset->institutionId ?? '',
                    implode(',', $dataset->formats),
                    $dataset->modified?->toDateString() ?? '',
                ], $page->items));

                foreach ($page->facets as $name => $buckets) {
                    $this->line(sprintf('<info>%s</info>: %s', $name, implode(', ', array_map(
                        static fn(FacetBucket $bucket): string => sprintf('%s (%d)', $bucket->label(), $bucket->count),
                        array_slice($buckets, 0, 10),
                    ))));
                }
            }
        } catch (DaneGovException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('%d match(es) in total.', $page->total));

        return self::SUCCESS;
    }
}
