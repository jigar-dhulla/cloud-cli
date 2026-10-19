<?php

namespace App\Concerns;

use App\Client\Requests\CreateDatabaseClusterRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabasePreset;
use App\Dto\DatabaseType;
use App\Dto\Region;
use App\Enums\DatabaseClusterPreset;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesDatabaseCluster
{
    protected ?string $databaseClusterPreset = null;

    protected function createDatabaseCluster(array $defaults = []): DatabaseCluster
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                    validate: fn ($v) => match (true) {
                        ! preg_match('/^[a-z0-9_-]+$/', $v) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                        strlen($v) < 2 => 'Must be at least 2 characters',
                        strlen($v) > 40 => 'Must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $types = collect($types);

        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Database type',
                    options: $types->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label])->toArray(),
                    default: $value ?? $defaults['type'] ?? null,
                    required: true,
                ),
            ),
        );

        $selectedType = $types->firstWhere('type', $this->form()->get('type'));

        // Newest first so the default lands on the version most users want.
        $versionOptions = collect($selectedType->versions)
            ->sort('version_compare')
            ->reverse()
            ->values();

        $this->form()->prompt(
            'version',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => select(
                        label: 'Version',
                        options: $versionOptions->mapWithKeys(fn (string $version) => [$version => $selectedType->label.' '.$version])->toArray(),
                        default: $value ?? $defaults['version'] ?? $versionOptions->first(),
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $defaults['version'] ?? $versionOptions->first()),
            'engine-version',
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $regionOptions = collect($regions)->filter(
            fn (Region $region) => in_array($region->value, $selectedType->regions),
        );

        $defaultRegion = $defaults['region'] ?? null;

        if ($defaultRegion !== null && ! in_array($defaultRegion, $selectedType->regions)) {
            $defaultRegion = null;
        }

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Region',
                    options: $regionOptions->mapWithKeys(fn (Region $region) => [
                        $region->value => $region->label,
                    ])->toArray(),
                    default: $value ?? $defaultRegion ?? $regionOptions->first()?->value,
                    required: true,
                ),
            ),
        );

        $config = $this->databaseClusterConfigFromPreset($selectedType) ?? $this->promptForDatabaseClusterConfig($selectedType);
        $clusterId = $this->hasOption('cluster') ? $this->option('cluster') : null;
        $createDefaultDatabase = $this->createDefaultDatabaseOption();

        return spin(
            fn () => $this->client->databaseClusters()->create(
                new CreateDatabaseClusterRequestData(
                    type: $this->form()->get('type'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    config: $config,
                    // Numeric keys come back from the prompt as integers.
                    version: (string) $this->form()->get('version'),
                    clusterId: $clusterId,
                    createDefaultDatabase: $createDefaultDatabase,
                ),
            ),
            'Creating database cluster...',
        );
    }

    protected function createDefaultDatabaseOption(): ?bool
    {
        $value = $this->hasOption('create-default-database') ? $this->option('create-default-database') : null;

        if ($value === null) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($parsed === null) {
            throw new RuntimeException('Invalid --create-default-database value "'.$value.'". Must be true or false.');
        }

        return $parsed;
    }

    protected function databaseClusterConfigFromPreset(DatabaseType $type): ?array
    {
        $region = $this->form()->get('region');

        $presets = collect(spin(
            fn () => $this->client->databaseClusters()->presets($type->type, $region),
            'Fetching database presets...',
        ))->keyBy('key');

        if ($presets->isEmpty() && ! $this->isInteractive()) {
            throw new RuntimeException(sprintf('No presets are available for database type "%s" in region "%s".', $type->type, $region));
        }

        if ($this->databaseClusterPreset === 'custom') {
            return null;
        }

        // The cached choice may not exist for a region picked on a later attempt.
        if ($this->databaseClusterPreset !== null && $presets->has($this->databaseClusterPreset)) {
            return $presets->get($this->databaseClusterPreset)->config;
        }

        $this->databaseClusterPreset = select(
            label: 'Configuration',
            options: $presets->map(fn (DatabasePreset $preset) => $preset->label)->put('custom', 'Custom')->toArray(),
            default: $presets->keys()->first() ?? 'custom',
            required: true,
            info: fn ($key) => $presets->has($key)
                ? $this->describeDatabasePreset($type, $presets->get($key))
                : 'Custom configuration',
        );

        if ($this->databaseClusterPreset === 'custom') {
            return null;
        }

        return $presets->get($this->databaseClusterPreset)->config;
    }

    protected function describeDatabasePreset(DatabaseType $type, DatabasePreset $preset): string
    {
        // Only some types have a hand-written summary; the rest (such as RDS) get their config listed as-is.
        if ($clusterPreset = DatabaseClusterPreset::tryFrom($type->type)) {
            return $clusterPreset->description()($preset->config);
        }

        return collect($preset->config)
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.var_export($value, true))
            ->map(fn ($line) => str_replace("'", '', $line))
            ->implode(' · ');
    }

    protected function promptForDatabaseClusterConfig(DatabaseType $type): array
    {
        $config = [];

        foreach ($type->configSchema as $field) {
            $schema = is_array($field) ? $field : $field->toArray();
            $name = $schema['name'];
            $fieldType = $schema['type'] ?? 'string';
            $required = $schema['required'] ?? false;
            $description = $schema['description'] ?? null;
            $min = $schema['min'] ?? null;
            $max = $schema['max'] ?? null;
            $enum = $schema['enum'] ?? [];
            $example = $schema['example'] ?? null;
            $key = 'config.'.$name;

            $label = match ($name) {
                'cu_min' => 'CU min',
                'cu_max' => 'CU max',
                default => str_replace('_', ' ', ucfirst($name)),
            };

            if (count($enum) > 0) {
                $options = is_array($enum) ? array_combine($enum, $enum) : $enum;
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => select(
                            label: $label,
                            options: $options,
                            default: $value ?? $example ?? array_key_first($options),
                            required: $required,
                        ),
                    ),
                );
            } elseif ($fieldType === 'boolean') {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => confirm(
                            label: $label,
                            default: filter_var($value ?? $example, FILTER_VALIDATE_BOOLEAN),
                            hint: $description,
                        ),
                    ),
                );
            } elseif ($fieldType === 'integer') {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => number(
                            label: $label,
                            default: $value ?? $example ?? 0,
                            required: $required,
                            hint: $description,
                            min: $min,
                            max: $max,
                        ),
                    ),
                );
            } else {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => text(
                            label: $label,
                            default: $value ?? $example ?? '',
                            required: $required,
                            hint: $description,
                        ),
                    ),
                );
            }
        }

        return collect($this->form()->filled())
            ->filter(fn ($value) => str_starts_with($value->key, 'config.'))
            ->mapWithKeys(fn ($value) => [
                str_replace('config.', '', $value->key) => $value->value(),
            ])->toArray();
    }

    protected function createDatabaseClusterWithOptions(string $type, string $version, DatabasePreset $preset, string $name, string $region): DatabaseCluster
    {
        return spin(
            fn () => $this->client->databaseClusters()->create(
                new CreateDatabaseClusterRequestData(
                    type: $type,
                    name: $name,
                    region: $region,
                    config: $preset->config,
                    version: $version,
                ),
            ),
            'Creating database cluster...',
        );
    }
}
