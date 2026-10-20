<?php

namespace App\Client\Resources\Caches;

use App\Dto\CacheType;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ListCacheTypesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/caches/types';
    }

    public function createDtoFromResponse(Response $response): array
    {
        return array_map(fn ($item) => CacheType::createFromResponse([
            'data' => $item,
        ]), $response->json('data') ?? []);
    }
}
