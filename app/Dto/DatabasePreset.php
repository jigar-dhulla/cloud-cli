<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class DatabasePreset extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $config,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return self::from([
            'key' => $data['key'],
            'label' => $data['label'],
            'config' => $data['config'] ?? [],
        ]);
    }
}
