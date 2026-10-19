<?php

namespace App\Client\Requests;

class BucketCorsSettingsData extends RequestData
{
    public function __construct(
        public readonly ?array $allowedOrigins = null,
        public readonly ?array $allowedMethods = null,
        public readonly ?array $allowedHeaders = null,
        public readonly ?array $exposeHeaders = null,
        public readonly ?int $maxAgeSeconds = null,
    ) {
        //
    }

    public function isEmpty(): bool
    {
        return $this->toRequestData() === [];
    }

    /**
     * Fill the settings this update doesn't change from the bucket's current ones, so the full
     * object goes out whether the API merges cors_settings or replaces it.
     *
     * @param  array<string, mixed>|null  $current
     */
    public function over(?array $current): self
    {
        return new self(
            allowedOrigins: $this->allowedOrigins ?? $current['allowed_origins'] ?? null,
            allowedMethods: $this->allowedMethods ?? $current['allowed_methods'] ?? null,
            allowedHeaders: $this->allowedHeaders ?? $current['allowed_headers'] ?? null,
            exposeHeaders: $this->exposeHeaders ?? $current['expose_headers'] ?? null,
            maxAgeSeconds: $this->maxAgeSeconds ?? $current['max_age_seconds'] ?? null,
        );
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'allowed_origins' => $this->allowedOrigins,
            'allowed_methods' => $this->allowedMethods,
            'allowed_headers' => $this->allowedHeaders,
            'expose_headers' => $this->exposeHeaders,
            'max_age_seconds' => $this->maxAgeSeconds,
        ]);
    }
}
