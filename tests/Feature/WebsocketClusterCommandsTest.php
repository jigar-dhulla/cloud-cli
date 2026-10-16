<?php

use App\Client\Connector;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Client\Resources\WebSocketClusters\CreateWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClusterTypesRequest;
use App\Client\Resources\WebSocketClusters\UpdateWebSocketClusterRequest;
use App\Commands\WebsocketClusterCreate;
use App\Commands\WebsocketClusterUpdate;
use App\ConfigRepository;
use App\Dto\WebsocketCluster;
use App\Enums\WebsocketServerStatus;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sent = new stdClass;
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupWebsocketClusterMocks(?array $cluster = null, ?array $types = null): void
{
    $cluster ??= websocketClusterResponse();
    $types ??= websocketClusterTypesResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make([
            'data' => [
                ['region' => 'us-east-1', 'label' => 'US East', 'flag' => 'us'],
                ['region' => 'us-east-2', 'label' => 'US East (Ohio)', 'flag' => 'us'],
                ['region' => 'eu-central-1', 'label' => 'EU Central', 'flag' => 'eu'],
            ],
        ], 200),
        ListWebSocketClusterTypesRequest::class => function () use ($types) {
            test()->sent->typeRequests = (test()->sent->typeRequests ?? 0) + 1;

            return MockResponse::make($types, 200);
        },
        CreateWebSocketClusterRequest::class => function (PendingRequest $request) use ($cluster) {
            test()->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $cluster], 201);
        },
        UpdateWebSocketClusterRequest::class => function (PendingRequest $request) use ($cluster) {
            test()->sent->body = $request->body()->all();

            return MockResponse::make(['data' => $cluster], 200);
        },
        ListWebSocketClustersRequest::class => MockResponse::make([
            'data' => [$cluster],
            'links' => ['next' => null],
        ], 200),
        GetWebSocketClusterRequest::class => MockResponse::make(['data' => $cluster], 200),
    ]);
}

it('builds the dto for every status the api can return', function (string $status) {
    $cluster = WebsocketCluster::createFromResponse([
        'data' => websocketClusterResponse(['attributes' => ['status' => $status]]),
    ]);

    expect($cluster->status)->toBe(WebsocketServerStatus::from($status));
})->with(['creating', 'updating', 'available', 'stopped', 'deleting', 'deleted', 'unknown']);

it('lists a stopped cluster', function () {
    setupWebsocketClusterMocks(websocketClusterResponse([
        'attributes' => ['status' => 'stopped'],
    ]));

    $this->artisan('websocket-cluster:list', ['--no-interaction' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('stopped');
});

it('gets a stopped cluster', function () {
    setupWebsocketClusterMocks(websocketClusterResponse([
        'attributes' => ['status' => 'stopped'],
    ]));

    $this->artisan('websocket-cluster:get', [
        'cluster' => 'wss-1',
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('stopped');
});

/**
 * @param  class-string  $command
 */
function websocketClusterCommand(string $command): object
{
    $instance = app($command);
    (new ReflectionProperty($instance, 'client'))->setValue($instance, new Connector('test-api-token'));

    return $instance;
}

function invokeWebsocketClusterCommand(object $command, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($command, $method))->invoke($command, ...$args);
}

it('lists the websocket cluster types from the api', function () {
    setupWebsocketClusterMocks();

    Artisan::call('websocket-cluster:types', ['--json' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output)->toHaveCount(1)
        ->and($output[0]['type'])->toBe('reverb')
        ->and($output[0]['available'])->toBeTrue()
        ->and($output[0]['regions'])->toBe(['us-east-1', 'eu-central-1'])
        ->and($output[0]['maxConnections'][3])->toBe(['value' => 2000, 'label' => '2,000 connections', 'available' => false]);
});

it('offers only the tiers the plan includes', function () {
    setupWebsocketClusterMocks();

    $command = websocketClusterCommand(WebsocketClusterUpdate::class);
    $type = invokeWebsocketClusterCommand($command, 'webSocketClusterType', 'reverb');

    expect(invokeWebsocketClusterCommand($command, 'maxConnectionOptions', $type))->toBe([
        200 => '200 connections',
        100 => '100 connections',
        500 => '500 connections',
    ]);
});

it('offers only the regions the cluster type supports', function () {
    setupWebsocketClusterMocks();

    $command = websocketClusterCommand(WebsocketClusterCreate::class);
    $type = invokeWebsocketClusterCommand($command, 'webSocketClusterType', 'reverb');
    $regions = (new Connector('test-api-token'))->meta()->regions();

    expect(invokeWebsocketClusterCommand($command, 'webSocketClusterRegionOptions', $type, $regions))->toBe([
        'us-east-1' => 'US East',
        'eu-central-1' => 'EU Central',
    ]);
});

it('creates a cluster with the smallest available tier when none is given', function () {
    setupWebsocketClusterMocks(types: websocketClusterTypesResponse([
        'max_connections' => [
            ['value' => 100, 'label' => '100 connections', 'available' => false],
            ['value' => 500, 'label' => '500 connections', 'available' => true],
            ['value' => 200, 'label' => '200 connections', 'available' => true],
        ],
    ]));

    $this->artisan('websocket-cluster:create', [
        '--name' => 'my-reverb',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe([
        'name' => 'my-reverb',
        'type' => 'reverb',
        'region' => 'us-east-1',
        'max_connections' => 200,
    ]);
});

it('creates a cluster with the given tier', function () {
    setupWebsocketClusterMocks();

    $this->artisan('websocket-cluster:create', [
        '--name' => 'my-reverb',
        '--region' => 'us-east-1',
        '--max-connections' => '500',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body['max_connections'])->toBe(500);
});

it('rejects a tier the api does not list', function (string $command, array $args) {
    setupWebsocketClusterMocks();

    $this->artisan($command, [...$args, '--max-connections' => '300', '--no-interaction' => true])
        ->assertFailed();

    expect($this->sent)->not->toHaveProperty('body');
})->with([
    'create' => ['websocket-cluster:create', ['--name' => 'my-reverb', '--region' => 'us-east-1']],
    'update' => ['websocket-cluster:update', ['cluster' => 'wss-1']],
]);

it('rejects a tier the plan does not include', function (string $command, array $args) {
    setupWebsocketClusterMocks();

    $this->artisan($command, [...$args, '--max-connections' => '2000', '--no-interaction' => true])
        ->assertFailed();

    expect($this->sent)->not->toHaveProperty('body');
})->with([
    'create' => ['websocket-cluster:create', ['--name' => 'my-reverb', '--region' => 'us-east-1']],
    'update' => ['websocket-cluster:update', ['cluster' => 'wss-1']],
]);

it('refuses to create a cluster of a type the organization cannot create', function () {
    setupWebsocketClusterMocks(types: websocketClusterTypesResponse(['available' => false]));

    $this->artisan('websocket-cluster:create', [
        '--name' => 'my-reverb',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->sent)->not->toHaveProperty('body');
});

it('updates max connections headlessly', function () {
    setupWebsocketClusterMocks();

    $this->artisan('websocket-cluster:update', [
        'cluster' => 'wss-1',
        '--max-connections' => '200',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe(['max_connections' => 200]);
});

it('renames a cluster without fetching cluster types', function () {
    setupWebsocketClusterMocks();

    $this->artisan('websocket-cluster:update', [
        'cluster' => 'wss-1',
        '--name' => 'renamed',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sent->body)->toBe(['name' => 'renamed'])
        ->and($this->sent)->not->toHaveProperty('typeRequests');
});
