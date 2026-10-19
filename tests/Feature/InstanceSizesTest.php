<?php

use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\ConfigRepository;
use Illuminate\Support\Facades\Artisan;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);

    MockClient::global([
        ListInstanceSizesRequest::class => MockResponse::make([
            'data' => [
                'general' => [
                    [
                        'name' => 'flex.c-1vcpu-256mb',
                        'label' => 'Flex 1 vCPU',
                        'description' => '1 vCPU / 256 MiB',
                        'cpu_type' => 'flex',
                        'compute_class' => 'general',
                        'cpu_count' => 1,
                        'memory_mib' => 256,
                    ],
                ],
                'managed_queue' => [
                    [
                        'name' => 'queue.quarter',
                        'label' => 'Queue Quarter',
                        'description' => '0.25 vCPU / 512 MiB',
                        'cpu_type' => 'flex',
                        'compute_class' => 'general',
                        'cpu_count' => 0.25,
                        'memory_mib' => 512,
                    ],
                ],
            ],
        ], 200),
    ]);
});

afterEach(function () {
    MockClient::destroyGlobal();
});

it('keeps fractional CPU counts in JSON output', function () {
    Artisan::call('instance:sizes', ['--json' => true, '--no-interaction' => true]);

    $sizes = json_decode(Artisan::output(), true);

    expect($sizes['categories']['general'][0]['cpuCount'])->toBe(1)
        ->and($sizes['categories']['managed_queue'][0]['cpuCount'])->toBe(0.25);
});
