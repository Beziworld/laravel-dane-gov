<?php

declare(strict_types=1);

namespace Beziworld\DaneGov\Console;

use Beziworld\DaneGov\DaneGovClient;
use Beziworld\DaneGov\Data\Column;
use Beziworld\DaneGov\Data\TabularRow;
use Beziworld\DaneGov\Exceptions\DaneGovException;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Shows a resource, previews its tabular rows and optionally downloads its file.
 */
final class ResourceCommand extends Command
{
    protected $signature = 'dane:resource
        {id : Resource id}
        {--rows=10 : Number of tabular rows to preview (0 skips the preview)}
        {--where=* : Column filters as column=value, for example col1=MAZOWIECKIE}
        {--q= : Raw query in the API syntax, for example "col3:KOWAL*"}
        {--sort= : Sort columns, for example -col4}
        {--download= : Save the file to this path or directory}
        {--format= : File format to download, for example csv}
        {--lang= : Language: pl or en}';

    protected $description = 'Show a dane.gov.pl resource, preview its rows or download its file';

    public function handle(DaneGovClient $client): int
    {
        $id = $this->argument('id');

        if (! is_numeric($id)) {
            $this->error('The resource id must be a number.');

            return self::INVALID;
        }

        try {
            $lang = $this->option('lang');
            $client = is_string($lang) && $lang !== '' ? $client->inLanguage($lang) : $client;
            $resource = $client->resource((int) $id);

            $this->line(sprintf('<info>%s</info> [%d]', $resource->title, $resource->id));
            $this->table(['Field', 'Value'], [
                ['Dataset', (string) ($resource->datasetId ?? '')],
                ['Institution', (string) ($resource->institutionId ?? '')],
                ['Format', $resource->format ?? ''],
                ['Files', implode(', ', $resource->availableFormats())],
                ['Size', $resource->fileSize !== null ? number_format($resource->fileSize, 0, '.', ' ') . ' B' : ''],
                ['Data date', $resource->dataDate?->toDateString() ?? ''],
                ['Modified', $resource->modified?->toDateTimeString() ?? ''],
                ['Tabular data', $resource->hasTabularData ? 'yes' : 'no'],
                ['Download URL', $resource->downloadUrl ?? ''],
            ]);

            $rows = max(0, (int) $this->option('rows'));

            if ($resource->hasTabularData && $rows > 0) {
                $this->preview($client, $resource->id, $rows);
            }

            $download = $this->option('download');

            if (is_string($download) && $download !== '') {
                $format = $this->option('format');
                $file = $client->download($resource, $download, is_string($format) && $format !== '' ? $format : null);
                $this->info(sprintf('Saved %s (%s B).', $file->path, number_format($file->bytes, 0, '.', ' ')));
            }
        } catch (DaneGovException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function preview(DaneGovClient $client, int $resourceId, int $rows): void
    {
        $reader = $client->table($resourceId)->perPage(min(100, $rows))->limit($rows);

        foreach ((array) $this->option('where') as $condition) {
            if (! is_string($condition) || ! str_contains($condition, '=')) {
                throw new InvalidArgumentException(sprintf('Use --where=column=value, got "%s".', is_scalar($condition) ? (string) $condition : ''));
            }

            [$column, $value] = explode('=', $condition, 2);
            $reader = $reader->where($column, $value);
        }

        $q = $this->option('q');
        $reader = is_string($q) && $q !== '' ? $reader->query($q) : $reader;
        $sort = $this->option('sort');
        $reader = is_string($sort) && $sort !== '' ? $reader->sort(...explode(',', $sort)) : $reader;

        $columns = $reader->columns();
        $this->line(sprintf('%d matching row(s).', $reader->count()));
        $this->table(
            array_map(static fn(Column $column): string => sprintf('%s (%s)', $column->label, $column->name), $columns),
            $reader->rows()->map(static fn(TabularRow $row): array => array_map(
                static fn(Column $column): string => $row->text($column->name) ?? '',
                $columns,
            ))->all(),
        );
    }
}
