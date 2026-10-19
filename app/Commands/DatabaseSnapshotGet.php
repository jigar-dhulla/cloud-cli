<?php

namespace App\Commands;

use App\Dto\DatabaseSnapshot;
use App\Support\Formatter;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseSnapshotGet extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseSnapshot::class;

    protected $signature = 'database-snapshot:get
                            {cluster? : The database cluster ID or name}
                            {snapshot? : The snapshot ID or name}';

    protected $description = 'Get database snapshot details';

    protected $aliases = ['db-snapshot:get'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database Snapshot Details');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $snapshot = $this->resolvers()->databaseSnapshot()->from($cluster, $this->argument('snapshot'));

        $snapshot = spin(
            fn () => $this->client->databaseSnapshots()->get($snapshot->id),
            'Fetching snapshot...',
        );

        $this->outputJsonIfWanted($snapshot);

        dataList([
            'ID' => $snapshot->id,
            'Name' => $snapshot->name,
            'Description' => $snapshot->description ?? '—',
            'Type' => $snapshot->type ?? '—',
            'Status' => $snapshot->status ?? '—',
            'Size' => $snapshot->storageBytes !== null ? Formatter::bytes($snapshot->storageBytes) : '—',
            'Point-in-time recovery' => $snapshot->pitrEnabled !== null ? ($snapshot->pitrEnabled ? 'Yes' : 'No') : '—',
            'PITR Ends At' => $snapshot->pitrEndsAt?->toIso8601String() ?? '—',
            'Created At' => $snapshot->createdAt?->toIso8601String() ?? '—',
            'Completed At' => $snapshot->completedAt?->toIso8601String() ?? '—',
        ]);
    }
}
