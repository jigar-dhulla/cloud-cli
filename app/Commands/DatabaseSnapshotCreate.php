<?php

namespace App\Commands;

use App\Client\Requests\CreateDatabaseSnapshotRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseSnapshot;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class DatabaseSnapshotCreate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseSnapshot::class;

    protected $signature = 'database-snapshot:create
                            {cluster? : The database cluster ID or name}
                            {--name= : The snapshot name (max 100 characters)}
                            {--description= : A description of the snapshot (3-40 characters)}';

    protected $description = 'Create a database snapshot';

    protected $aliases = ['db-snapshot:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Database Snapshot');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $snapshot = $this->loopUntilValid(fn () => $this->createSnapshot($cluster));

        $this->outputJsonIfWanted($snapshot);

        success('Database snapshot created');
    }

    protected function createSnapshot(DatabaseCluster $cluster)
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Name',
                    default: $value ?? '',
                    required: true,
                    validate: fn (string $name) => strlen($name) > 100
                        ? 'The name cannot exceed 100 characters.'
                        : null,
                ),
            ),
        );

        $this->form()->prompt(
            'description',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => textarea(
                        label: 'Description',
                        placeholder: 'Optional',
                        default: $value ?? '',
                        validate: fn (string $description) => match (true) {
                            $description === '' => null,
                            strlen($description) < 3 => 'The description must be at least 3 characters.',
                            strlen($description) > 40 => 'The description cannot exceed 40 characters.',
                            default => null,
                        },
                    ),
                )
                ->nonInteractively(fn () => ''),
        );

        $description = $this->form()->get('description');

        return spin(
            fn () => $this->client->databaseSnapshots()->create(
                new CreateDatabaseSnapshotRequestData(
                    $cluster->id,
                    $this->form()->get('name'),
                    $description === '' ? null : $description,
                ),
            ),
            'Creating snapshot...',
        );
    }
}
