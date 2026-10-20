<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class UsageDatabase extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $identifier,
        public readonly string $type,
        public readonly UsageDatabaseMetric $storage,
        public readonly UsageDatabaseMetric $compute,
        public readonly ?UsageDatabaseMetric $dataTransfer,
        public readonly ?UsageDatabaseMetric $backup,
        public readonly int $totalCents,
    ) {
        //
    }

    public static function fromApiResponse(array $item): self
    {
        return new self(
            name: $item['name'] ?? '',
            identifier: $item['identifier'] ?? '',
            type: $item['type'] ?? '',
            storage: UsageDatabaseMetric::fromApiResponse($item['storage'] ?? []),
            compute: UsageDatabaseMetric::fromApiResponse($item['compute'] ?? []),
            dataTransfer: UsageDatabaseMetric::fromNullableApiResponse($item['data_transfer'] ?? null),
            backup: UsageDatabaseMetric::fromNullableApiResponse($item['backup'] ?? null),
            totalCents: $item['total_cents'] ?? 0,
        );
    }
}
