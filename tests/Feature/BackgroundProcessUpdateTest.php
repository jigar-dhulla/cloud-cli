<?php

use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Tests\Fixtures\InteractiveBackgroundProcessUpdateCommand;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function backgroundProcessResponse(array $attributes = []): array
{
    return ['data' => [
        'id' => 'process-1',
        'type' => 'backgroundProcesses',
        'attributes' => array_merge([
            'type' => 'worker',
            'processes' => 1,
            'command' => 'php artisan queue:work database --queue=default --tries=1',
            'config' => [
                'connection' => 'database',
                'queue' => 'default',
                'tries' => 1,
                'backoff' => 30,
                'sleep' => 10,
                'rest' => 0,
                'timeout' => 60,
                'force' => false,
            ],
            'strategy_type' => 'none',
            'strategy_threshold' => null,
            'created_at' => '2026-01-01T00:00:00.000000Z',
        ], $attributes),
        'relationships' => ['instance' => ['data' => ['id' => 'inst-1', 'type' => 'instances']]],
    ]];
}

function setupBackgroundProcessUpdateMocks(array $attributes = [], string $instanceType = 'app'): stdClass
{
    $sent = new stdClass;
    $sent->body = null;

    $process = backgroundProcessResponse($attributes);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetBackgroundProcessRequest::class => MockResponse::make($process, 200),
        GetInstanceRequest::class => MockResponse::make(['data' => [
            'id' => 'inst-1',
            'type' => 'instances',
            'attributes' => [
                'name' => 'jobs',
                'type' => $instanceType,
                'size' => 'flex.c-1vcpu-256mb',
                'scaling_type' => 'none',
                'min_replicas' => 0,
                'max_replicas' => 1,
                'uses_scheduler' => false,
            ],
        ]], 200),
        UpdateBackgroundProcessRequest::class => function (PendingRequest $request) use ($sent, $process) {
            $sent->body = $request->body()->all();

            return MockResponse::make($process, 200);
        },
    ]);

    return $sent;
}

it('switches a worker to a custom process', function () {
    $sent = setupBackgroundProcessUpdateMocks();

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'custom',
        '--command' => 'php artisan reverb:start',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toBe([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
    ]);
});

it('switches a custom process to a worker with the settings given', function () {
    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'worker',
        '--connection' => 'redis',
        '--queue' => 'emails',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toBe([
        'type' => 'worker',
        'config' => [
            'connection' => 'redis',
            'queue' => 'emails',
        ],
    ]);
});

it('keeps the stored worker config when switching back to a worker', function () {
    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'worker',
        '--tries' => '5',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body['type'])->toBe('worker')
        ->and($sent->body['config'])->toMatchArray([
            'connection' => 'database',
            'queue' => 'default',
            'tries' => 5,
            'timeout' => 60,
        ]);
});

it('leaves the config to the API when switching to a worker without settings', function () {
    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'worker',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toBe(['type' => 'worker']);
});

it('asks for worker settings when switching to a worker from the update menu', function () {
    Artisan::registerCommand(new InteractiveBackgroundProcessUpdateCommand);

    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    Prompt::fake([
        // Pick Type from the update menu, then Worker.
        Key::DOWN, Key::SPACE, Key::ENTER,
        Key::UP, Key::ENTER,
        // Pick Connection and Queue from the worker settings.
        Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER,
        ...str_split('redis'), Key::ENTER,
        ...str_split('emails'), Key::ENTER,
    ]);

    $this->artisan('test:interactive-background-process-update', ['process' => 'process-1'])->assertSuccessful();

    expect($sent->body)->toBe([
        'type' => 'worker',
        'config' => ['connection' => 'redis', 'queue' => 'emails'],
    ]);
});

it('asks for the command when --type switches to custom interactively', function () {
    Artisan::registerCommand(new InteractiveBackgroundProcessUpdateCommand);

    $sent = setupBackgroundProcessUpdateMocks();

    Prompt::fake([
        ...str_split('php artisan reverb:start'), Key::ENTER,
        // Confirm the update.
        Key::ENTER,
    ]);

    $this->artisan('test:interactive-background-process-update', [
        'process' => 'process-1',
        '--type' => 'custom',
    ])->assertSuccessful();

    expect($sent->body)->toBe([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
    ]);
});

it('lets --type switch to a worker interactively without a stored config', function () {
    Artisan::registerCommand(new InteractiveBackgroundProcessUpdateCommand);

    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    Prompt::fake([
        // Set no worker settings, then confirm the update.
        Key::ENTER,
        Key::ENTER,
    ]);

    $this->artisan('test:interactive-background-process-update', [
        'process' => 'process-1',
        '--type' => 'worker',
    ])->assertSuccessful();

    expect($sent->body)->toBe(['type' => 'worker']);
});

it('leaves the type out when it does not change', function () {
    $sent = setupBackgroundProcessUpdateMocks();

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'worker',
        '--processes' => '3',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toBe(['processes' => 3]);
});

it('leaves the command alone when updating a custom process', function () {
    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--processes' => '3',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toBe(['processes' => 3]);
});

it('keeps the stored worker config when changing one setting', function () {
    $sent = setupBackgroundProcessUpdateMocks();

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--tries' => '5',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toEqual([
        'type' => 'worker',
        'config' => [
            'connection' => 'database',
            'queue' => 'default',
            'tries' => 5,
            'backoff' => 30,
            'sleep' => 10,
            'rest' => 0,
            'timeout' => 60,
            'force' => false,
        ],
    ]);
});

function managedQueueWorkerAttributes(): array
{
    return [
        'command' => 'php artisan queue:work cloud --queue=jobs --sleep=0',
        'config' => [
            'connection' => 'cloud',
            'queue' => 'jobs',
            'tries' => 1,
            'backoff' => 30,
            'sleep' => 0,
            'rest' => 0,
            'timeout' => 60,
            'force' => false,
        ],
    ];
}

it('leaves out the settings a managed queue rejects when changing one setting', function () {
    $sent = setupBackgroundProcessUpdateMocks(managedQueueWorkerAttributes(), 'managed_queue');

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--tries' => '5',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->body)->toEqual([
        'type' => 'worker',
        'config' => [
            'tries' => 5,
            'backoff' => 30,
            'timeout' => 60,
            'force' => false,
        ],
    ]);
});

it('rejects the options a managed queue sets itself', function () {
    $sent = setupBackgroundProcessUpdateMocks(managedQueueWorkerAttributes(), 'managed_queue');

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--queue' => 'emails',
        '--sleep' => '3',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
});
