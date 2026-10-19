<?php

namespace App\Commands;

use App\Enums\InstanceType;
use App\Exceptions\CommandExitException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ManagedQueueFailedJobDelete extends BaseCommand
{
    protected $signature = 'managed-queue:delete-failed-job
                            {instance? : The instance ID}
                            {jobs?* : One or more failed job IDs}
                            {--failed-before= : Delete every job that failed before this date and time}
                            {--force : Skip confirmation}';

    protected $description = 'Delete failed jobs from a managed queue';

    protected $aliases = ['queue:delete-failed-job'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Failed Jobs');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $jobIds = $this->jobIds();
        $failedBefore = $this->option('failed-before');

        if ($jobIds->isNotEmpty() && $failedBefore !== null) {
            $this->failAndExit('Pass job IDs or --failed-before, not both.');
        }

        if ($jobIds->isEmpty() && $failedBefore === null) {
            $this->ensureInteractive('Provide one or more job IDs, or --failed-before.');

            [$jobIds, $failedBefore] = $this->promptForJobs($instance->id);
        }

        $this->confirmDestructive(
            $failedBefore !== null
                ? "Delete every job that failed before {$failedBefore} on '{$instance->name}'?"
                : "Delete {$jobIds->count()} failed ".Str::plural('job', $jobIds->count())." from '{$instance->name}'?",
        );

        if ($failedBefore === null && $jobIds->count() === 1) {
            spin(
                fn () => $this->client->instances()->deleteFailedJob($instance->id, $jobIds->first()),
                'Deleting failed job...',
            );

            $this->outputJsonIfWanted('Failed job deleted.');

            success('Failed job deleted');

            return;
        }

        spin(
            fn () => $this->client->instances()->deleteFailedJobs($instance->id, $jobIds->all(), $failedBefore),
            'Deleting failed jobs...',
        );

        // The bulk endpoint returns 202 and deletes in the background.
        $this->outputJsonIfWanted('Failed jobs queued for deletion.');

        success('Failed jobs queued for deletion');
    }

    /**
     * @return Collection<int, string>
     */
    protected function jobIds(): Collection
    {
        // Accept comma-separated values too, since that is how the API takes them.
        return collect($this->argument('jobs'))
            ->flatMap(fn (string $value) => explode(',', $value))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array{0: Collection<int, string>, 1: string|null}
     */
    protected function promptForJobs(string $instanceId): array
    {
        $mode = select(
            label: 'Which failed jobs do you want to delete?',
            options: [
                'select' => 'Choose jobs from the list',
                'before' => 'Every job that failed before a date and time',
            ],
        );

        if ($mode === 'before') {
            $failedBefore = text(
                label: 'Delete jobs that failed before',
                placeholder: now()->subDay()->toDateTimeString(),
                required: true,
            );

            return [collect(), $failedBefore];
        }

        $jobs = spin(
            fn () => $this->client->instances()->failedJobs($instanceId)->collect(),
            'Fetching failed jobs...',
        );

        if ($jobs->isEmpty()) {
            $this->outputErrorOrThrow('No failed jobs found.');

            throw new CommandExitException(self::FAILURE);
        }

        $selected = multiselect(
            label: 'Select failed jobs to delete',
            options: $jobs->mapWithKeys(fn ($job) => [
                $job->id => "{$job->name} ({$job->queue}, failed at {$job->failedAt?->format('Y-m-d H:i:s')})",
            ])->toArray(),
            required: true,
        );

        return [collect($selected)->values(), null];
    }
}
