<?php

namespace App\Client\Resources;

use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;
use Saloon\PaginationPlugin\Paginator;

class DedicatedClustersResource extends Resource
{
    public function list(): Paginator
    {
        $request = new ListDedicatedClustersRequest;

        return $this->paginate($request);
    }
}
