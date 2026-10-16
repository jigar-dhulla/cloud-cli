<?php

namespace App\Commands;

use App\Dto\Environment;
use App\Dto\Secret;
use Illuminate\Support\Collection;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class EnvironmentSecretDetach extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment-secret:detach
                            {environment? : The environment ID or name}
                            {secrets?* : The secret IDs to detach}
                            {--force : Skip confirmation}';

    protected $description = 'Detach secrets from an environment without deleting them';

    public function handle()
    {
        $this->ensureClient();

        intro('Detaching Secrets');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $secrets = $this->resolveSecrets($environment);

        $keys = collect($secrets)->pluck('key')->join(', ');

        $this->confirmDestructive("Detach {$keys} from {$environment->name}? The secrets themselves are kept.");

        $updatedEnvironment = spin(
            fn () => collect($secrets)
                ->map(fn (Secret $secret) => $this->client->environments()->detachSecret($environment->id, $secret->id))
                ->last(),
            'Detaching secrets...',
        );

        $this->outputJsonIfWanted($updatedEnvironment);

        success("Secrets detached: {$keys}");
    }

    /**
     * @return list<Secret>
     */
    protected function resolveSecrets(Environment $environment): array
    {
        // The environment carries its secrets as an include; there is no endpoint of their own.
        $attached = spin(
            fn () => collect($this->client->environments()->include('secrets')->get($environment->id)->secrets),
            'Fetching secrets...',
        );

        if ($attached->isEmpty()) {
            $this->failAndExit('No secrets attached to this environment.');
        }

        $identifiers = $this->argument('secrets');

        if ($identifiers === []) {
            return $this->selectSecrets($attached);
        }

        $resolver = $this->resolvers()->secret();

        // Only IDs are accepted: secret names are not unique within an organization.
        return collect($identifiers)->map(function (string $identifier) use ($attached, $resolver) {
            $secret = $resolver->fromCollection($attached, $identifier);

            if (! $secret) {
                $this->failAndExit("Secret {$identifier} is not attached to this environment. Run `cloud environment-secret:list --json` to see the attached secret IDs.");
            }

            return $secret;
        })->unique('id')->values()->all();
    }

    /**
     * @return list<Secret>
     */
    protected function selectSecrets(Collection $secrets): array
    {
        // Names are not unique, so each option carries its ID.
        $options = $secrets->mapWithKeys(fn (Secret $secret) => [$secret->id => "{$secret->key} ({$secret->id})"])->toArray();

        if (! $this->isInteractive()) {
            $this->failAndExit('At least one secret is required. Provide secret IDs as arguments. Attached secrets: '.implode(', ', array_keys($options)).'.');
        }

        $selected = multiselect(
            label: 'Secrets',
            options: $options,
            required: true,
        );

        return $secrets->whereIn('id', $selected)->values()->all();
    }
}
