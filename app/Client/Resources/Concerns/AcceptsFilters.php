<?php

namespace App\Client\Resources\Concerns;

trait AcceptsFilters
{
    /**
     * @param  array<string, string|null>  $filters  Keyed by the API's filter name, e.g. ['status' => 'running'] for filter[status]; blank values are left out.
     */
    public function filter(array $filters): static
    {
        foreach ($filters as $key => $value) {
            if (filled($value)) {
                $this->query()->add("filter[{$key}]", $value);
            }
        }

        return $this;
    }

    public function sort(?string $sort): static
    {
        if (filled($sort)) {
            $this->query()->add('sort', $sort);
        }

        return $this;
    }
}
