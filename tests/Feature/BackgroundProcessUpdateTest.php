<?php

use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

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

function setupBackgroundProcessUpdateMocks(array $attributes = []): stdClass
{
    $sent = new stdClass;
    $sent->body = null;

    $process = backgroundProcessResponse($attributes);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetBackgroundProcessRequest::class => MockResponse::make($process, 200),
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

it('switches a custom process to a worker with its full config', function () {
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

it('requires worker options when switching to a worker without a stored config', function () {
    $sent = setupBackgroundProcessUpdateMocks([
        'type' => 'custom',
        'command' => 'php artisan reverb:start',
        'config' => [],
    ]);

    $this->artisan('background-process:update', [
        'process' => 'process-1',
        '--type' => 'worker',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->body)->toBeNull();
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
