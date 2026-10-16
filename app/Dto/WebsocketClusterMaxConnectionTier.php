<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class WebsocketClusterMaxConnectionTier extends Data
{
    public function __construct(
        public readonly int $value,
        public readonly string $label,
        public readonly bool $available,
    ) {
        //
    }
}
