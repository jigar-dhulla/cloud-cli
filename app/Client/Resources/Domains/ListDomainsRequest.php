<?php

namespace App\Client\Resources\Domains;

use App\Client\Resources\Concerns\AcceptsFilters;
use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Domain;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDomainsRequest extends Request implements Paginatable
{
    use AcceptsFilters;
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/domains";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($domain) => Domain::createFromResponse([
            'data' => $domain,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
