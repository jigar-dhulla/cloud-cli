<?php

namespace App\Commands;

use App\Client\Requests\UpdateCacheRequestData;
use App\Concerns\ValidatesCacheInput;
use App\Dto\Cache;
use App\Dto\CacheType;
use App\Enums\CacheEvictionPolicy;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheUpdate extends BaseCommand
{
    use ValidatesCacheInput;

    protected ?string $jsonDataClass = Cache::class;

    protected $signature = 'cache:update
                            {cache? : The cache ID or name}
                            {--name= : Cache name}
                            {--size= : Cache size}
                            {--auto-upgrade-enabled= : Enable auto upgrade}
                            {--is-public= : Whether cache is public}
                            {--eviction-policy= : Eviction policy}
                            {--uses-hibernation= : Hibernate the cache when idle}
                            {--hibernation-timeout= : Minutes idle before hibernating (1-60), implies --uses-hibernation}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a cache';

    /**
     * @var array<CacheType>|null
     */
    protected ?array $types = null;

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $this->defineFields($cache);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedCache = $this->runUpdate(
            fn () => $this->updateCache($cache),
            fn () => $this->collectDataAndUpdate($cache),
        );

        $this->outputJsonIfWanted($updatedCache);

        success("Cache updated: {$updatedCache->name}");
    }

    protected function updateCache(Cache $cache): Cache
    {
        $autoUpgradeEnabled = $this->form()->get('auto_upgrade_enabled');
        $isPublic = $this->form()->get('is_public');
        $usesHibernation = $this->form()->boolean('uses_hibernation');
        $hibernationTimeout = $this->form()->get('hibernation_timeout');

        // A timeout on its own means the user wants hibernation.
        if ($usesHibernation === null && filled($hibernationTimeout)) {
            $usesHibernation = true;
        }

        // The API requires a timeout whenever hibernation is sent as on, even if the cache already has one.
        if ($usesHibernation && blank($hibernationTimeout)) {
            $hibernationTimeout = $cache->hibernationTimeout
                ?? $this->failAndExit('Pass --hibernation-timeout (1-60 minutes) to enable hibernation.');
        }

        spin(
            fn () => $this->client->caches()->update(
                new UpdateCacheRequestData(
                    cacheId: $cache->id,
                    name: $this->form()->get('name'),
                    size: $this->form()->get('size'),
                    autoUpgradeEnabled: $autoUpgradeEnabled !== null ? filter_var($autoUpgradeEnabled, FILTER_VALIDATE_BOOLEAN) : null,
                    isPublic: $isPublic !== null ? filter_var($isPublic, FILTER_VALIDATE_BOOLEAN) : null,
                    evictionPolicy: $this->form()->get('eviction_policy'),
                    usesHibernation: $usesHibernation,
                    hibernationTimeout: filled($hibernationTimeout) ? (int) $hibernationTimeout : null,
                ),
            ),
            'Updating cache...',
        );

        return $this->client->caches()->get($cache->id);
    }

    protected function defineFields(Cache $cache): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    required: true,
                    default: $value ?? $cache->name,
                    validate: fn ($v) => $this->validateCacheName($v),
                ),
            ),
        )->setPreviousValue($cache->name);

        $this->form()->define(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Size',
                    options: $this->sizeOptions($cache),
                    default: $value ?? $cache->size,
                ),
            ),
        )->setPreviousValue($cache->size);

        $this->form()->define(
            'auto_upgrade_enabled',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Auto upgrade enabled?',
                    default: (bool) ($value ?? $cache->autoUpgradeEnabled),
                ),
            ),
            'auto-upgrade-enabled',
        )->setPreviousValue($cache->autoUpgradeEnabled ? 'true' : 'false');

        $this->form()->define(
            'is_public',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Public?',
                    default: (bool) ($value ?? $cache->isPublic),
                ),
            ),
            'is-public',
        )->setPreviousValue($cache->isPublic ? 'true' : 'false');

        $this->form()->define(
            'eviction_policy',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Eviction policy',
                    options: CacheEvictionPolicy::options(),
                    default: $value ?? $cache->evictionPolicy ?? CacheEvictionPolicy::ALLKEYS_LRU->value,
                ),
            ),
        )->setPreviousValue($cache->evictionPolicy);

        $this->form()->define(
            'uses_hibernation',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Hibernate when idle?',
                    default: $value !== null ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $cache->usesHibernation,
                ),
            ),
            'uses-hibernation',
        )->setLabel('Hibernation')->setPreviousValue($cache->usesHibernation ? 'true' : 'false');

        $this->form()->define(
            'hibernation_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Minutes idle before hibernating',
                    default: (string) ($value ?? $cache->hibernationTimeout ?? '5'),
                    required: true,
                    hint: 'From 1 to 60',
                    validate: fn ($v) => $this->validateHibernationTimeout($v),
                ),
            ),
            'hibernation-timeout',
        )->setPreviousValue($cache->hibernationTimeout !== null ? (string) $cache->hibernationTimeout : null);
    }

    /**
     * @return array<CacheType>
     */
    protected function types(): array
    {
        return $this->types ??= spin(
            fn () => $this->client->caches()->types(),
            'Fetching cache types...',
        );
    }

    protected function cacheType(Cache $cache): ?CacheType
    {
        return collect($this->types())->first(fn (CacheType $type) => $type->type === $cache->type);
    }

    /**
     * @return array<string>
     */
    protected function unsupportedFields(Cache $cache): array
    {
        $type = $this->cacheType($cache);

        return array_values(array_filter([
            $type?->supportsEvictionPolicy ? null : 'eviction_policy',
            $type?->supportsHibernationFor($cache->size) ? null : 'uses_hibernation',
            $type?->supportsHibernationFor($cache->size) ? null : 'hibernation_timeout',
        ]));
    }

    protected function sizeOptions(Cache $cache): array
    {
        $type = $this->cacheType($cache);

        if (! $type) {
            $this->failAndExit("Unable to find sizes for cache type: {$cache->type}");
        }

        return collect($type->sizes)->mapWithKeys(fn ($size) => [$size->value => $size->label])->toArray();
    }

    protected function collectDataAndUpdate(Cache $cache): Cache
    {
        $unsupported = $this->unsupportedFields($cache);

        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())
                ->reject(fn ($field) => in_array($field->key, $unsupported))
                ->mapWithKeys(fn ($field) => [
                    $field->key => $field->label(),
                ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        // Switching hibernation on is the natural moment to pick a timeout, and the API needs one, so ask without making the user select it too.
        if ((! $cache->usesHibernation || $cache->hibernationTimeout === null) && $this->form()->boolean('uses_hibernation') && ! in_array('hibernation_timeout', $selection)) {
            $this->form()->prompt('hibernation_timeout');
        }

        return $this->updateCache($cache);
    }
}
