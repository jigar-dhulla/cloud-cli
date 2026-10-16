<?php

use App\Client\Resources\Environments\DetachEnvironmentSecretRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
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

function setupEnvironmentSecretDetachMocks(?array $attached = null): stdClass
{
    $sent = new stdClass;
    $sent->urls = [];

    $attached ??= [
        secretResponse(),
        secretResponse(['id' => 'secret-2', 'attributes' => ['key' => 'MAILGUN_SECRET']]),
    ];

    $environment = [
        'data' => createEnvironmentResponse(['relationships' => [
            'secrets' => ['data' => array_map(fn ($secret) => ['id' => $secret['id'], 'type' => 'secrets'], $attached)],
        ]]),
        'included' => $attached,
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        GetEnvironmentRequest::class => MockResponse::make($environment, 200),
        DetachEnvironmentSecretRequest::class => function (PendingRequest $request) use ($sent) {
            $sent->urls[] = $request->getUrl();
            $sent->method = $request->getMethod()->value;

            return MockResponse::make(['data' => createEnvironmentResponse()], 200);
        },
    ]);

    return $sent;
}

it('detaches a secret by ID', function () {
    $sent = setupEnvironmentSecretDetachMocks();

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        'secrets' => ['secret-2'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->method)->toBe('DELETE')
        ->and($sent->urls)->toHaveCount(1)
        ->and($sent->urls[0])->toEndWith('/environments/env-1/secrets/secret-2');
});

it('detaches each secret given once', function () {
    $sent = setupEnvironmentSecretDetachMocks();

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1', 'secret-2', 'secret-1'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($sent->urls)->toHaveCount(2)
        ->and($sent->urls[0])->toEndWith('/secrets/secret-1')
        ->and($sent->urls[1])->toEndWith('/secrets/secret-2');
});

it('requires --force when non-interactive', function () {
    $sent = setupEnvironmentSecretDetachMocks();

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1'],
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->urls)->toBeEmpty();
});

it('rejects a secret that is not attached to the environment', function () {
    $sent = setupEnvironmentSecretDetachMocks([secretResponse()]);

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        'secrets' => ['secret-2'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->urls)->toBeEmpty();
});

it('rejects a secret name', function () {
    $sent = setupEnvironmentSecretDetachMocks();

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        // Names are not unique, so only IDs are accepted.
        'secrets' => ['STRIPE_KEY'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->urls)->toBeEmpty();
});

it('fails when no secrets are given non-interactively', function () {
    $sent = setupEnvironmentSecretDetachMocks();

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->urls)->toBeEmpty();
});

it('fails when the environment has no secrets attached', function () {
    $sent = setupEnvironmentSecretDetachMocks([]);

    $this->artisan('environment-secret:detach', [
        'environment' => 'env-1',
        'secrets' => ['secret-1'],
        '--force' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($sent->urls)->toBeEmpty();
});
