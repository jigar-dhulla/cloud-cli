<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageDatabaseMetric extends Data
{
    public function __construct(
        public readonly int $cents,
        public readonly int|float $units,
        public readonly string $unitLabel,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            cents: $item['cents'] ?? 0,
            units: $item['units'] ?? 0,
            unitLabel: $item['unit_label'] ?? '',
        );
    }

    public static function fromNullableApiResponse(?array $item): ?self
    {
        return $item === null ? null : self::fromApiResponse($item);
    }
}
