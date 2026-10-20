<?php

namespace App\Concerns;

use App\Dto\DatabaseType;
use RuntimeException;

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

        [$filter, $expected] = match ($fieldType) {
            'integer' => [FILTER_VALIDATE_INT, 'a whole number'],
            'number' => [FILTER_VALIDATE_FLOAT, 'a number'],
            'boolean' => [FILTER_VALIDATE_BOOLEAN, 'true or false'],
            default => [null, null],
        };

        if ($filter === null) {
            return $value;
        }

        $parsed = filter_var($value, $filter, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            throw new RuntimeException(sprintf('Invalid --%s value "%s". Must be %s.', str_replace('_', '-', $key), $value, $expected));
        }

        return $parsed;
    }
}
