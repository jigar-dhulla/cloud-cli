<?php

namespace App\Dto;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class WebsocketClusterType extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly bool $available,
        public readonly array $regions,
        #[DataCollectionOf(WebsocketClusterMaxConnectionTier::class)]
        public readonly array $maxConnections,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return self::from([
            'type' => $data['type'] ?? '',
            'label' => $data['label'] ?? '',
            'available' => $data['available'] ?? false,
            'regions' => $data['regions'] ?? [],
            'maxConnections' => $data['max_connections'] ?? [],
        ]);
    }

    /**
     * @return array<WebsocketClusterMaxConnectionTier>
     */
    public function availableTiers(): array
    {
        return array_values(array_filter(
            $this->maxConnections,
            fn (WebsocketClusterMaxConnectionTier $tier) => $tier->available,
        ));
    }
}
