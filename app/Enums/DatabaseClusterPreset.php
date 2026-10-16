<?php

namespace App\Enums;

use Closure;

/**
 * The database types the CLI offers opinionated presets for.
 *
 * The presets themselves come from GET /databases/presets, since sizes vary by
 * region and plan.
 *
 * The API describes a database by a versionless type plus a separate engine
 * version (see GET /databases/types), so the cases here mirror the API's type
 * values and carry no version of their own. The retired versioned identifiers
 * (`neon_serverless_postgres_18`, `laravel_mysql_8`, ...) are still accepted as
 * input and split into a type and version by `fromLegacyType()`.
 */
enum DatabaseClusterPreset: string
{
    case LaravelMysql = 'laravel_mysql';
    case NeonServerlessPostgres = 'neon_serverless_postgres';

    /**
     * Resolve a retired versioned type identifier into a type and version.
     *
     * @return array{0: self, 1: string}|null
     */
    public static function fromLegacyType(string $type): ?array
    {
        if (! preg_match('/^(?<type>[a-z_]+?)_(?<version>\d+)$/', $type, $matches)) {
            return null;
        }

        $preset = self::tryFrom($matches['type']);

        if ($preset === null) {
            return null;
        }

        // The retired MySQL types squashed the version into the identifier, but the
        // API expects it dotted and 8.4 is the only creatable release.
        $version = match ([$preset, $matches['version']]) {
            [self::LaravelMysql, '8'], [self::LaravelMysql, '84'] => '8.4',
            default => $matches['version'],
        };

        return [$preset, $version];
    }

    public function description(): Closure
    {
        return match ($this) {
            self::LaravelMysql => fn ($preset) => sprintf(
                '%s · %sGB storage · %d %s backups',
                str($preset['size'])
                    ->replaceMatches(
                        '/^mysql-(pro|flex)-(\d+)(gb|mb)$/',
                        '$1 ($2 $3 RAM)',
                    )
                    ->replace('gb', 'GiB')
                    ->replace('mb', 'MiB')
                    ->ucfirst()
                    ->toString(),
                $preset['storage'],
                $preset['retention_days'],
                str('day')->plural($preset['retention_days']),
            ),
            self::NeonServerlessPostgres => fn ($preset) => sprintf(
                '%s vCPU units · %s · %s',
                $preset['cu_min'] === $preset['cu_max'] ? $this->formatNumber($preset['cu_min']) : $this->formatNumber($preset['cu_min']).' – '.$this->formatNumber($preset['cu_max']),
                $preset['suspend_seconds'] > 0 ? 'Scale to zero after '.$preset['suspend_seconds'].' seconds' : 'No scale to zero',
                $preset['retention_days'] === 0 ? 'No backups' : $preset['retention_days'].' days PITR',
            ),
        };
    }

    protected function formatNumber(string $number): string
    {
        return match ($number) {
            '0.25' => '¼',
            '0.5' => '½',
            default => $number,
        };
    }
}
