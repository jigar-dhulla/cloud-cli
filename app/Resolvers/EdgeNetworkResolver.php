<?php

namespace App\Resolvers;

use App\Dto\EdgeNetwork;
use Illuminate\Support\Collection;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class EdgeNetworkResolver extends Resolver
{
    protected const ORGANIZATION_DEFAULT = 'organization-default';

    /**
     * @var Collection<int, EdgeNetwork>|null
     */
    protected ?Collection $networks = null;

    /**
     * Null means "let the API use the organization's preferred edge network".
     */
    public function from(?string $idOrName = null): ?EdgeNetwork
    {
        if ($idOrName === null || $idOrName === '') {
            return $this->fromInput();
        }

        $network = $this->fetchAndFind($idOrName);

        if (! $network) {
            $this->failAndExit("Unable to resolve edge network: {$idOrName}. Run `cloud edge-network:list --json` to see available edge networks.");
        }

        $this->displayResolved('Edge network', $network->name, $network->id);

        return $network;
    }

    public function fromInput(): ?EdgeNetwork
    {
        if (! $this->isInteractive) {
            return null;
        }

        // The user didn't ask for an edge network, so a failed lookup shouldn't block the create.
        try {
            $networks = $this->fetchAll();
        } catch (RequestException|FatalRequestException) {
            return null;
        }

        // Every organization has the shared network, so a single result leaves nothing to choose.
        if ($networks->count() <= 1) {
            return null;
        }

        $selected = select(
            label: 'Edge network',
            options: [self::ORGANIZATION_DEFAULT => 'Organization default']
                + $networks->mapWithKeys(fn (EdgeNetwork $network) => [$network->id => $network->name])->toArray(),
            default: self::ORGANIZATION_DEFAULT,
            info: fn ($id) => $id === self::ORGANIZATION_DEFAULT ? '' : $id,
        );

        return $networks->firstWhere('id', $selected);
    }

    public function fetchAndFind(string $identifier): ?EdgeNetwork
    {
        return $this->fetchAll()->firstWhere('id', $identifier)
            ?? $this->fetchAll()->firstWhere('name', $identifier);
    }

    /**
     * @return Collection<int, EdgeNetwork>
     */
    protected function fetchAll(): Collection
    {
        return $this->networks ??= spin(
            fn () => $this->client->edgeNetworks()->list(),
            'Fetching edge networks...',
        )->collect()->collect();
    }

    protected function idPrefix(): callable
    {
        // There is no endpoint for a single edge network, so identifiers are always matched against the list.
        return fn () => false;
    }
}
