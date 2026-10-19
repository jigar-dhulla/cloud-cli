<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\Databases\GetDatabaseRequest;
use App\Client\Resources\Databases\ListDatabasesRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);

    $cluster = databaseClusterResponse(['id' => 'db-1']);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make($cluster, 200),
        ListDatabaseClustersRequest::class => MockResponse::make([
            'data' => [$cluster['data']],
            'included' => $cluster['included'] ?? [],
            'links' => ['next' => null],
        ], 200),
        ListDatabasesRequest::class => MockResponse::make([
            'data' => [databaseSchemaResponse(['attributes' => ['status' => 'creating']])],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseRequest::class => MockResponse::make([
            'data' => databaseSchemaResponse(['attributes' => ['status' => 'creating']]),
        ], 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('includes the status when listing databases', function () {
    Artisan::call('database:list', ['cluster' => 'db-1', '--json' => true, '--no-interaction' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output[0]['status'])->toBe('creating');
});

it('includes the status when getting a database', function () {
    Artisan::call('database:get', ['cluster' => 'db-1', 'database' => 'schema-1', '--json' => true, '--no-interaction' => true]);

    $output = json_decode(Artisan::output(), true);

    expect($output['status'])->toBe('creating');
});
