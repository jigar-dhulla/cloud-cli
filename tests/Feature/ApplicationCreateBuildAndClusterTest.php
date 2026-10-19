<?php

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\ConfigRepository;
use App\Dto\Application;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function () {
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('hasRemote')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('remoteHost')->andReturn('github.com')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('user/my-app')->byDefault();
    $this->app->instance(Git::class, $this->mockGit);

    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    $this->sentBody = null;

    $application = [
        'data' => createApplicationResponse(),
        'included' => [
            organizationResponse()['data'],
            createEnvironmentResponse(),
        ],
    ];

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListRegionsRequest::class => MockResponse::make(regionsResponse(), 200),
        ListApplicationsRequest::class => MockResponse::make(['data' => [], 'included' => [], 'links' => ['next' => null]], 200),
        CreateApplicationRequest::class => function (PendingRequest $request) use ($application) {
            $this->sentBody = $request->body()?->all();

            return MockResponse::make($application, 200);
        },
        GetApplicationRequest::class => MockResponse::make($application, 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('sends the build command and cluster when creating an application', function () {
    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--build-command' => 'npm run build',
        '--cluster' => 'cluster-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)
        ->toMatchArray(['build_command' => 'npm run build', 'cluster_id' => 'cluster-1']);
});

it('omits the build command and cluster when creating an application without them', function () {
    $this->artisan('application:create', [
        '--name' => 'My App',
        '--repository' => 'user/my-app',
        '--region' => 'us-east-1',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($this->sentBody)->not->toHaveKeys(['build_command', 'cluster_id']);
});

it('sends the build command and cluster when shipping', function () {
    try {
        Artisan::call('ship', [
            '--name' => 'My App',
            '--region' => 'us-east-1',
            '--build-command' => 'npm run build',
            '--cluster' => 'cluster-1',
            '--no-interaction' => true,
        ]);
    } catch (Throwable) {
        // Only the creation step is mocked. The rest of the ship flow is out of scope here.
    }

    expect($this->sentBody)
        ->toMatchArray(['build_command' => 'npm run build', 'cluster_id' => 'cluster-1']);
});

it('reads notes, framework and language from the application resource', function () {
    $application = Application::createFromResponse([
        'data' => createApplicationResponse(['attributes' => [
            'notes' => 'Marketing site',
            'framework' => 'laravel',
            'language' => 'php',
        ]]),
    ]);

    expect($application->notes)->toBe('Marketing site')
        ->and($application->framework)->toBe('laravel')
        ->and($application->language)->toBe('php');

    expect(Application::createFromResponse(['data' => createApplicationResponse()]))
        ->notes->toBeNull()
        ->framework->toBeNull()
        ->language->toBeNull();
});
