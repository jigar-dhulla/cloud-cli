<?php

namespace App\Concerns;

use App\Client\Requests\CreateWebSocketClusterRequestData;
use App\Dto\Region;
use App\Dto\WebsocketCluster;
use App\Dto\WebsocketClusterType;
use App\Enums\WebsocketServerType;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesWebSocketCluster
{
    use InteractsWithWebSocketClusterTypes;

    protected function createWebSocketCluster(array $defaults = []): WebsocketCluster
    {
        $type = $this->creatableWebSocketClusterType();

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Cluster name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                ),
            ),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Region',
                    options: $this->webSocketClusterRegionOptions($type, $regions),
                    default: $value ?? $defaults['region'] ?? null,
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'max_connections',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Max connections',
                    options: $this->maxConnectionOptions($type),
                    default: $value ?? $defaults['max_connections'] ?? $this->defaultMaxConnections($type),
                    required: true,
                ),
            ),
        );

        return spin(
            fn () => $this->client->websocketClusters()->create(
                new CreateWebSocketClusterRequestData(
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    maxConnections: $this->form()->integer('max_connections'),
                ),
            ),
            'Creating WebSocket cluster...',
        );
    }

    protected function createWebSocketClusterWithOptions(array $options): WebsocketCluster
    {
        $type = $this->creatableWebSocketClusterType();

        $name = $options['name'] ?? '';
        $region = $options['region'] ?? null;
        $maxConnections = isset($options['max_connections'])
            ? $this->validateMaxConnections($type, $options['max_connections'])
            : $this->defaultMaxConnections($type);

        return spin(
            fn () => $this->client->websocketClusters()->create(
                new CreateWebSocketClusterRequestData(
                    name: $name,
                    region: $region,
                    maxConnections: $maxConnections,
                ),
            ),
            'Creating WebSocket cluster...',
        );
    }

    protected function creatableWebSocketClusterType(): WebsocketClusterType
    {
        $type = $this->webSocketClusterType(WebsocketServerType::REVERB->value);

        if (! $type->available) {
            $this->failAndExit("Your organization cannot create {$type->label} clusters.");
        }

        return $type;
    }

    /**
     * @param  array<Region>  $regions
     * @return array<string, string>
     */
    protected function webSocketClusterRegionOptions(WebsocketClusterType $type, array $regions): array
    {
        $labels = collect($regions)->mapWithKeys(fn (Region $r) => [$r->value => $r->label]);

        return collect($type->regions)
            ->mapWithKeys(fn (string $region) => [$region => $labels->get($region, $region)])
            ->toArray();
    }

    protected function defaultMaxConnections(WebsocketClusterType $type): int
    {
        return min(array_keys($this->maxConnectionOptions($type)));
    }
}
