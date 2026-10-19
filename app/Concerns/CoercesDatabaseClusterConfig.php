<?php

namespace App\Concerns;

use App\Dto\DatabaseType;

trait CoercesDatabaseClusterConfig
{
    protected function coerceConfigValue(string $key, mixed $value, DatabaseType $type): mixed
    {
        $schemaField = collect($type->configSchema)->firstWhere('name', $key);

        if (! $schemaField) {
            return $value;
        }

        $schema = is_array($schemaField) ? $schemaField : $schemaField->toArray();
        $fieldType = $schema['type'] ?? 'string';

        // Nullable fields such as storage_autoscale_max_gb use null to turn the setting off.
        if (is_string($value) && strtolower($value) === 'null' && in_array($fieldType, ['integer', 'number'], true)) {
            return null;
        }

        return match ($fieldType) {
            'integer' => (int) $value,
            'number' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
