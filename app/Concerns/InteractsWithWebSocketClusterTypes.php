<?php

namespace App\Concerns;

use App\Dto\WebsocketClusterMaxConnectionTier;
use App\Dto\WebsocketClusterType;

use function Laravel\Prompts\spin;

trait InteractsWithWebSocketClusterTypes
{
    /**
     * @var array<WebsocketClusterType>|null
     */
    protected ?array $webSocketClusterTypes = null;

    protected function webSocketClusterType(string $type): WebsocketClusterType
    {
        $types = $this->webSocketClusterTypes ??= spin(
            fn () => $this->client->websocketClusters()->types(),
            'Fetching WebSocket cluster types...',
        );

        $clusterType = collect($types)->first(fn (WebsocketClusterType $t) => $t->type === $type);

        if (! $clusterType) {
            $this->failAndExit("Unable to find WebSocket cluster type: {$type}");
        }

        return $clusterType;
    }

    /**
     * @return array<int, string>
     */
    protected function maxConnectionOptions(WebsocketClusterType $type): array
    {
        $options = collect($type->availableTiers())
            ->mapWithKeys(fn (WebsocketClusterMaxConnectionTier $tier) => [$tier->value => $tier->label])
            ->toArray();

        if ($options === []) {
            $this->failAndExit("Your plan does not include any max connection tiers for {$type->label} clusters.");
        }

        return $options;
    }

    protected function validateMaxConnections(WebsocketClusterType $type, mixed $value): int
    {
        $tier = collect($type->maxConnections)->first(
            fn (WebsocketClusterMaxConnectionTier $tier) => (string) $tier->value === (string) $value,
        );

        $allowed = implode(', ', array_keys($this->maxConnectionOptions($type)));

        if (! $tier) {
            $this->failAndExit("Invalid max connections: {$value}. Allowed values: {$allowed}.");
        }

        if (! $tier->available) {
            $this->failAndExit("Your plan does not include {$tier->value} max connections. Allowed values: {$allowed}.");
        }

        return $tier->value;
    }
}
