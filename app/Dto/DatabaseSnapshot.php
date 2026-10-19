<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class DatabaseSnapshot extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $status = null,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
        public readonly ?int $storageBytes = null,
        public readonly ?bool $pitrEnabled = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $pitrEndsAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $completedAt = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'name' => $attributes['name'] ?? $data['id'],
            'createdAt' => $attributes['created_at'] ?? null,
            'status' => $attributes['status'] ?? null,
            'description' => $attributes['description'] ?? null,
            'type' => $attributes['type'] ?? null,
            'storageBytes' => $attributes['storage_bytes'] ?? null,
            'pitrEnabled' => $attributes['pitr_enabled'] ?? null,
            'pitrEndsAt' => $attributes['pitr_ends_at'] ?? null,
            'completedAt' => $attributes['completed_at'] ?? null,
        ]);
    }
}
