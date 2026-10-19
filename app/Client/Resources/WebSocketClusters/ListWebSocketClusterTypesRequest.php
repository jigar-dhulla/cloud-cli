<?php

namespace App\Client\Resources\WebSocketClusters;

use App\Dto\WebsocketClusterType;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ListWebSocketClusterTypesRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/websocket-servers/types';
    }

    public function createDtoFromResponse(Response $response): array
    {
        return array_map(fn (array $item) => WebsocketClusterType::createFromResponse([
            'data' => $item,
        ]), $response->json('data') ?? []);
    }
}
