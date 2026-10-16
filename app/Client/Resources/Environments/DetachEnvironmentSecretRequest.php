<?php

namespace App\Client\Resources\Environments;

use App\Dto\Environment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class DetachEnvironmentSecretRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $environmentId,
        protected string $secretId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/secrets/{$this->secretId}";
    }

    public function createDtoFromResponse(Response $response): Environment
    {
        return Environment::createFromResponse($response->json());
    }
}
