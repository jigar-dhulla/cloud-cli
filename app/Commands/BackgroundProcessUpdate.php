<?php

namespace App\Commands;

use App\Client\Requests\UpdateBackgroundProcessRequestData;
use App\Dto\BackgroundProcess;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BackgroundProcessUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = BackgroundProcess::class;

    protected $signature = 'background-process:update
                            {process? : The background process ID}
                            {--type= : Process type (worker|custom)}
                            {--command= : The command to run (custom type only)}
                            {--processes= : Number of processes (1-10)}
                            {--connection= : Queue connection (worker only)}
                            {--queue= : Queue name(s), comma-separated (worker only)}
                            {--tries= : Number of job attempts (worker only)}
                            {--backoff= : Seconds before retry (worker only)}
                            {--sleep= : Seconds to sleep when no jobs (worker only)}
                            {--rest= : Seconds to rest between jobs (worker only)}
                            {--timeout= : Job timeout in seconds (worker only)}
                            {--run-in-maintenance= : Run in maintenance mode (worker only)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a background process';

    protected $aliases = ['process:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Background Process');

        $process = $this->resolvers()->backgroundProcess()->from($this->argument('process'));

        $this->defineFields($process);

        // A type passed as a flag skips the update menu, so ask for what the new type needs here.
        if ($this->isInteractive() && $this->typeChanged($process)) {
            $this->promptForNewTypeFields($process);
        }

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedProcess = $this->runUpdate(
            fn () => $this->updateProcess($process),
            fn () => $this->collectDataAndUpdate($process),
        );

        $this->outputJsonIfWanted($updatedProcess);

        success("Background process updated: {$updatedProcess->id}");
    }

    protected function updateProcess(BackgroundProcess $process): BackgroundProcess
    {
        $data = new UpdateBackgroundProcessRequestData(
            backgroundProcessId: $process->id,
            type: $this->typeChanged($process) ? $this->targetType($process) : null,
            processes: $this->form()->integer('processes'),
            command: $this->form()->get('command'),
            config: $this->buildConfig($process),
        );

        spin(
            fn () => $this->client->backgroundProcesses()->update($data),
            'Updating background process...',
        );

        return $this->client->backgroundProcesses()->get($process->id);
    }

    /**
     * @return array{connection?: string, queue?: string, tries?: int, backoff?: int, sleep?: int, rest?: int, timeout?: int, force?: bool}|null
     */
    protected function buildConfig(BackgroundProcess $process): ?array
    {
        if ($this->targetType($process) !== 'worker') {
            return null;
        }

        $config = collect($this->form()->filled())
            ->filter(fn ($field) => str_starts_with($field->key, 'config.'))
            ->mapWithKeys(fn ($field) => [
                str_replace('config.', '', $field->key) => $field->value(),
            ])
            ->toArray();

        if ($config === []) {
            return null;
        }

        // A config in the request replaces the stored one, so a switch back to a worker keeps the settings it had.
        return $this->typeChanged($process) ? array_merge($process->config ?? [], $config) : $config;
    }

    protected function targetType(BackgroundProcess $process): string
    {
        return $this->form()->get('type') ?? $process->type;
    }

    protected function typeChanged(BackgroundProcess $process): bool
    {
        return $this->targetType($process) !== $process->type;
    }

    protected function defineFields(BackgroundProcess $process): void
    {
        $this->form()->define(
            'processes',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Processes',
                    required: true,
                    default: (string) ($value ?? $process->processes),
                    min: 1,
                    max: 10,
                ),
            ),
        )->setPreviousValue((string) $process->processes);

        $this->form()->define(
            'type',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Type',
                    options: [
                        'worker' => 'Worker',
                        'custom' => 'Custom',
                    ],
                    default: $value ?? $process->type,
                    required: true,
                ),
            ),
        )->setPreviousValue($process->type);

        $this->defineTypeFields($process, $this->targetType($process));
    }

    protected function defineTypeFields(BackgroundProcess $process, string $type): void
    {
        if ($type === 'custom') {
            $this->form()->define(
                'command',
                fn ($resolver) => $resolver->fromInput(
                    fn ($value) => text(
                        label: 'Command',
                        required: true,
                        // A worker's command is generated from its config, so it's no starting point for a custom one.
                        default: $value ?? ($process->type === 'custom' ? $process->command : ''),
                    ),
                ),
            )->setPreviousValue($process->command);
        } elseif ($type === 'worker') {
            $this->defineWorkerConfigFields($process);
        }
    }

    protected function defineWorkerConfigFields(BackgroundProcess $process): void
    {
        $this->form()->define(
            'config.connection',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Connection',
                    required: true,
                    default: $value ?? $process->connection ?? '',
                ),
            )->setLabel('Connection'),
            'connection',
        )->setPreviousValue($process->connection);

        $this->form()->define(
            'config.queue',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Queue',
                    required: true,
                    default: $value ?? $process->queue ?? '',
                    hint: 'Comma-separated for multiple queues',
                ),
            )->setLabel('Queue'),
            'queue',
        )->setPreviousValue($process->queue);

        $this->form()->define(
            'config.tries',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Tries',
                    required: true,
                    default: $value ?? $process->tries ?? '',
                    hint: 'Number of times a job should be attempted',
                ),
            )->setLabel('Tries'),
            'tries',
        )->setPreviousValue($process->tries);

        $this->form()->define(
            'config.backoff',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Backoff',
                    required: true,
                    default: $value ?? $process->backoff ?? '',
                    hint: 'Seconds before retrying a failed job',
                ),
            )->setLabel('Backoff'),
            'backoff',
        )->setPreviousValue($process->backoff);

        $this->form()->define(
            'config.sleep',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Sleep',
                    required: true,
                    default: $value ?? $process->sleep ?? '',
                    hint: 'Seconds to sleep when no jobs available',
                ),
            )->setLabel('Sleep'),
            'sleep',
        )->setPreviousValue($process->sleep);

        $this->form()->define(
            'config.rest',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Rest',
                    required: true,
                    default: $value ?? $process->rest ?? '',
                    hint: 'Seconds to rest between jobs',
                ),
            )->setLabel('Rest'),
            'rest',
        )->setPreviousValue($process->rest);

        $this->form()->define(
            'config.timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Timeout',
                    required: true,
                    default: $value ?? $process->timeout ?? '',
                    hint: 'Seconds a job can run before timing out',
                ),
            )->setLabel('Timeout'),
            'timeout',
        )->setPreviousValue($process->timeout);

        $this->form()->define(
            'config.force',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Run in maintenance mode?',
                    default: (bool) ($value ?? $process->force ?? false),
                    hint: 'Force the worker to run even in maintenance mode',
                ),
            )->setLabel('Run in maintenance mode'),
            'run-in-maintenance',
        )->setPreviousValue($process->force);
    }

    protected function promptForNewTypeFields(BackgroundProcess $process): void
    {
        $type = $this->targetType($process);

        $this->defineTypeFields($process, $type);

        if ($type === 'custom') {
            $this->form()->prompt('command');

            return;
        }

        // Every worker setting is optional: the API falls back to the stored config, then to queue:work's defaults.
        $options = collect($this->form()->defined())
            ->filter(fn ($field) => str_starts_with($field->key, 'config.') && ! array_key_exists($field->key, $this->form()->filled()))
            ->mapWithKeys(fn ($field) => [$field->key => $field->label()])
            ->toArray();

        $selection = multiselect(
            label: 'Which worker settings do you want to set?',
            options: $options,
        );

        foreach ($selection as $key) {
            $this->form()->prompt($key);
        }
    }

    protected function collectDataAndUpdate(BackgroundProcess $process): BackgroundProcess
    {
        $options = collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
            $field->key => $field->label(),
        ])->toArray();

        if (empty($options)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        $selection = multiselect(
            label: 'What do you want to update?',
            options: $options,
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        if ($this->typeChanged($process)) {
            $this->promptForNewTypeFields($process);
        }

        return $this->updateProcess($process);
    }
}
