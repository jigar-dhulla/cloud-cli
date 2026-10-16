<?php

use App\Client\Resources\Applications\DeleteApplicationAvatarRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->deleted = null;

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetApplicationRequest::class => MockResponse::make(['data' => createApplicationResponse()], 200),
        DeleteApplicationAvatarRequest::class => function (PendingRequest $request) {
            $this->deleted = [$request->getMethod()->value, $request->getUrl()];

            return MockResponse::make('', 204);
        },
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('removes the avatar with --force', function () {
    $this->artisan('application:delete-avatar', [
        'application' => 'app-123',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->deleted[0])->toBe('DELETE')
        ->and($this->deleted[1])->toEndWith('/applications/app-123/avatar');
});

it('requires --force when non-interactive', function () {
    $this->artisan('application:delete-avatar', [
        'application' => 'app-123',
        '--no-interaction' => true,
    ])->assertFailed();

    expect($this->deleted)->toBeNull();
});

it('reports success as JSON', function () {
    $exitCode = Artisan::call('application:delete-avatar', [
        'application' => 'app-123',
        '--force' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Application avatar removed.');
});

it('fails when the API refuses', function () {
    MockClient::global()->addResponse(MockResponse::make(['message' => 'Forbidden'], 403), DeleteApplicationAvatarRequest::class);

    $this->artisan('application:delete-avatar', [
        'application' => 'app-123',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();
});
