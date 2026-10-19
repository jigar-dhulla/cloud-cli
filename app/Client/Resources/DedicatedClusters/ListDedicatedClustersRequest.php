<?php

namespace App\Client\Resources\DedicatedClusters;

use App\Client\Resources\Concerns\AcceptsFilters;
use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DedicatedCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDedicatedClustersRequest extends Request implements Paginatable
{
    use AcceptsFilters;
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/dedicated-clusters';
    }

    public function createDtoFromResponse(Response $response): array
    {
        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => DedicatedCluster::createFromResponse(['data' => $item]), $data);
    }
}
