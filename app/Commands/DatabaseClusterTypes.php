<?php

namespace App\Commands;

use App\Dto\DatabaseType;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class DatabaseClusterTypes extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseType::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'database-cluster:types';

    protected $description = 'List available database types and versions';

    protected $aliases = ['database:types'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database Types');

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        // The API also lists retired types that baked the version into the name; they
        // carry no versions and only remain so older clients keep working.
        $items = collect($types)->filter(fn (DatabaseType $type) => $type->versions !== [])->values();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No database types found.');

            return self::SUCCESS;
        }

        $rows = $items->map(fn (DatabaseType $type, $index) => [
            $type->label,
            $type->type,
            implode(', ', $type->versions),
            implode(PHP_EOL, collect($type->regions)->when($index < $items->count() - 1, fn ($regions) => $regions->push(''))->toArray()),
        ])->toArray();

        table(
            headers: ['Type', 'Value', 'Versions', 'Regions'],
            rows: $rows,
        );
    }
}
