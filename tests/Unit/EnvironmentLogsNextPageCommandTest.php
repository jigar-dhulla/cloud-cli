<?php

use App\Commands\EnvironmentLogs;
use Carbon\CarbonImmutable;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function environmentLogsNextPageCommand(array $input): string
{
    $command = app(EnvironmentLogs::class);
    $command->setApplication(new Application);

    $input = new ArrayInput($input, $command->getDefinition());

    // The command reads its options off the bound input, which is normally set by
    // the console kernel when the command is actually run.
    (fn () => $this->input = $input)->call($command);
    (fn () => $this->output = new OutputStyle($input, new BufferedOutput))->call($command);

    return (fn () => $this->nextPageCommand(
        'app-123',
        'env-1',
        CarbonImmutable::parse('2026-10-15T12:00:00Z'),
        CarbonImmutable::parse('2026-10-16T12:00:00Z'),
        'abc123==',
    ))->call($command);
}

it('builds a copyable next page command on one line', function () {
    expect(environmentLogsNextPageCommand([]))->toBe(
        'cloud environment:logs app-123 env-1 --from=2026-10-15T12:00:00+00:00 --to=2026-10-16T12:00:00+00:00 --cursor=abc123==',
    );
});

it('carries the filters into the next page command', function () {
    $command = environmentLogsNextPageCommand([
        '--type' => 'application',
        '--query' => 'payment failed',
        '--tail' => '50',
        '--case-sensitive' => true,
        '--whole-word' => true,
        '--instance' => ['inst-1', 'inst-2'],
    ]);

    expect($command)->toBe(
        'cloud environment:logs app-123 env-1 --from=2026-10-15T12:00:00+00:00 --to=2026-10-16T12:00:00+00:00 --cursor=abc123== '
        ."--type=application --query='payment failed' --tail=50 --case-sensitive --whole-word --instance=inst-1 --instance=inst-2",
    );
});

it('leaves out filters that were not passed', function () {
    expect(environmentLogsNextPageCommand(['--type' => 'access']))
        ->not->toContain('--query')
        ->not->toContain('--case-sensitive')
        ->not->toContain('--instance')
        ->toContain('--type=access');
});
