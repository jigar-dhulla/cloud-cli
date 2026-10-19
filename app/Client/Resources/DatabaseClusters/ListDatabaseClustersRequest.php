<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Client\Resources\Concerns\AcceptsFilters;
use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseCluster;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDatabaseClustersRequest extends Request implements Paginatable
{
    use AcceptsFilters;
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/databases/clusters';
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($cluster) => DatabaseCluster::createFromResponse([
            'data' => $cluster,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
