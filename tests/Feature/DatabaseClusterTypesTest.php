<?php

use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $config = Mockery::mock(ConfigRepository::class);
    $config->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $config);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('lists the database types and versions from the api', function () {
    $types = versionlessDatabaseTypesResponse();
    $types['data'][] = [
        'type' => 'laravel_mysql_8',
        'label' => 'Laravel MySQL 8.0',
        'versions' => [],
        'regions' => ['us-east-1'],
        'config_schema' => [],
    ];

    MockClient::global([
        ListDatabaseTypesRequest::class => MockResponse::make($types, 200),
    ]);

    Artisan::call('database:types', ['--json' => true, '--no-interaction' => true]);

    $output = collect(json_decode(Artisan::output(), true));

    expect($output->pluck('type')->all())->toBe(['laravel_mysql', 'aws_rds_postgres', 'neon_serverless_postgres'])
        ->and($output->firstWhere('type', 'neon_serverless_postgres')['versions'])->toBe(['16', '17', '18']);
});
