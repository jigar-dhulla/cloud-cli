<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateObjectStorageBucketRequestData;
use App\Client\Requests\UpdateObjectStorageBucketRequestData;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\DeleteObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
use App\Dto\ObjectStorageBucket;
use Saloon\PaginationPlugin\Paginator;

class ObjectStorageBucketsResource extends Resource
{
    public function list(?string $type = null, ?string $status = null, ?string $visibility = null): Paginator
    {
        $request = new ListObjectStorageBucketsRequest(
            type: $type,
            status: $status,
            visibility: $visibility,
        );

        return $this->paginate($request);
    }

    public function get(string $bucketId): ObjectStorageBucket
    {
        $request = new GetObjectStorageBucketRequest($bucketId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateObjectStorageBucketRequestData $data): ObjectStorageBucket
    {
        $request = new CreateObjectStorageBucketRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateObjectStorageBucketRequestData $data): ObjectStorageBucket
    {
        $request = new UpdateObjectStorageBucketRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $bucketId): void
    {
        $this->send(new DeleteObjectStorageBucketRequest($bucketId));
    }

    public function withDefaultIncludes(): static
    {
        return $this->include('keys');
    }
}
