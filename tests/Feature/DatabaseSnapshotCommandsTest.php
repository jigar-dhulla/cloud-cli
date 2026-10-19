<?php

use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

function setupDatabaseSnapshotMocks(): void
{
    $snapshot = databaseSnapshotResponse();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make([
            'data' => [$snapshot],
            'links' => ['next' => null],
        ], 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(['data' => $snapshot], 200),
        DeleteDatabaseSnapshotRequest::class => MockResponse::make([], 204),
        CreateDatabaseSnapshotRequest::class => MockResponse::make(['data' => $snapshot], 201),
    ]);
}

it('fetches a snapshot from the canonical endpoint', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:get', [
        'cluster' => 'db-123',
        'snapshot' => 'snap-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof GetDatabaseSnapshotRequest
            && $request->resolveEndpoint() === '/database-snapshots/snap-1';
    });
});

it('surfaces every snapshot attribute in json output', function () {
    $snapshot = databaseSnapshotResponse(['attributes' => [
        'description' => 'Before migration',
        'type' => 'manual',
        'storage_bytes' => 1048576,
        'pitr_enabled' => true,
        'pitr_ends_at' => '2026-01-08T00:00:00.000000Z',
        'completed_at' => '2026-01-01T00:05:00.000000Z',
    ]]);

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetDatabaseClusterRequest::class => MockResponse::make(databaseClusterResponse(), 200),
        ListDatabaseSnapshotsRequest::class => MockResponse::make(['data' => [$snapshot], 'links' => ['next' => null]], 200),
        GetDatabaseSnapshotRequest::class => MockResponse::make(['data' => $snapshot], 200),
    ]);

    Artisan::call('database-snapshot:get', ['cluster' => 'db-123', 'snapshot' => 'snap-1', '--json' => true]);

    expect(json_decode(Artisan::output(), true))->toMatchArray([
        'description' => 'Before migration',
        'type' => 'manual',
        'storageBytes' => 1048576,
        'pitrEnabled' => true,
        'pitrEndsAt' => '2026-01-08T00:00:00.000000Z',
        'completedAt' => '2026-01-01T00:05:00.000000Z',
    ]);
});

it('deletes a snapshot at the canonical endpoint', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:delete', [
        'cluster' => 'db-123',
        'snapshot' => 'snap-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof DeleteDatabaseSnapshotRequest
            && $request->resolveEndpoint() === '/database-snapshots/snap-1';
    });
});

it('still lists snapshots under the cluster', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:list', [
        'cluster' => 'db-123',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof ListDatabaseSnapshotsRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/snapshots';
    });
});

it('creates a snapshot headlessly from the name and description options', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-123',
        '--name' => 'before-migration',
        '--description' => 'Pre-deploy backup',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateDatabaseSnapshotRequest
            && $request->resolveEndpoint() === '/databases/clusters/db-123/snapshots'
            && $request->body()->all() === ['name' => 'before-migration', 'description' => 'Pre-deploy backup'];
    });
});

it('omits the description when none is given headlessly', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-123',
        '--name' => 'before-migration',
        '--no-interaction' => true,
    ])->assertSuccessful();

    MockClient::global()->assertSent(function ($request) {
        return $request instanceof CreateDatabaseSnapshotRequest
            && $request->body()->all() === ['name' => 'before-migration'];
    });
});

it('requires a name when creating a snapshot headlessly', function () {
    setupDatabaseSnapshotMocks();

    $this->artisan('database-snapshot:create', [
        'cluster' => 'db-123',
        '--no-interaction' => true,
    ])->assertFailed();

    MockClient::global()->assertNotSent(CreateDatabaseSnapshotRequest::class);
});
