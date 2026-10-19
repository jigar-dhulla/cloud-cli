<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Database extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $status = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'name' => $attributes['name'],
            'status' => $attributes['status'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ]);
    }
}
