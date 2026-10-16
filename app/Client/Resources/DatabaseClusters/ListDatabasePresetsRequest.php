<?php

namespace App\Client\Resources\DatabaseClusters;

use App\Dto\DatabasePreset;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class ListDatabasePresetsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $type,
        protected string $region,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/databases/presets';
    }

    protected function defaultQuery(): array
    {
        return [
            'type' => $this->type,
            'region' => $this->region,
        ];
    }

    /**
     * @return list<DatabasePreset>
     */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $item) => DatabasePreset::createFromResponse(['data' => $item]),
            $response->json()['data'] ?? [],
        );
    }
}
