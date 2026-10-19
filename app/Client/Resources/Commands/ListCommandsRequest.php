<?php

namespace App\Client\Resources\Commands;

use App\Client\Resources\Concerns\AcceptsFilters;
use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Command;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListCommandsRequest extends Request implements Paginatable
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
        return "/environments/{$this->environmentId}/commands";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($command) => Command::createFromResponse([
            'data' => $command,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
