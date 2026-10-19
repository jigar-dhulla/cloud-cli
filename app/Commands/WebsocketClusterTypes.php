<?php

namespace App\Commands;

use App\Dto\WebsocketClusterMaxConnectionTier;
use App\Dto\WebsocketClusterType;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class WebsocketClusterTypes extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketClusterType::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'websocket-cluster:types';

    protected $description = 'List available WebSocket cluster types, regions, and max connection tiers';

    protected $aliases = ['ws-cluster:types'];

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Cluster Types');

        $types = spin(
            fn () => $this->client->websocketClusters()->types(),
            'Fetching WebSocket cluster types...',
        );

        $items = collect($types);

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No WebSocket cluster types found.');

            return self::SUCCESS;
        }

        $rows = $items->map(fn (WebsocketClusterType $type, $index) => [
            $type->label,
            $type->type,
            $type->available ? 'Yes' : 'No',
            implode(PHP_EOL, collect($type->regions)->when($index < $items->count() - 1, fn ($regions) => $regions->push(''))->toArray()),
            implode(PHP_EOL, collect($type->maxConnections)
                ->map(fn (WebsocketClusterMaxConnectionTier $tier) => $tier->available ? $tier->label : "{$tier->label} (not on plan)")
                ->when($index < $items->count() - 1, fn ($tiers) => $tiers->push(''))
                ->toArray()),
        ])->toArray();

        table(
            headers: ['Type', 'Value', 'Available', 'Regions', 'Max Connections'],
            rows: $rows,
        );
    }
}
