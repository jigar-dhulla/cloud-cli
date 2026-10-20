<?php

use App\Client\Connector;
use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Client\Resources\Applications\UpdateApplicationAvatarRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

it('sends the avatar as multipart form data', function () {
    $sent = null;

    $connector = new Connector('test-api-token');
    $connector->withMockClient(new MockClient([
        UpdateApplicationAvatarRequest::class => function (PendingRequest $request) use (&$sent) {
            $sent = $request->createPsrRequest();

            return MockResponse::make(['data' => createApplicationResponse()], 200);
        },
    ]));

    $connector->send(new UpdateApplicationAvatarRequest(new UpdateApplicationAvatarRequestData('app-123', ['png-bytes', 'png'])));

    $boundary = str($sent->getHeaderLine('Content-Type'))->after('multipart/form-data; boundary=')->toString();

    expect($sent->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=')
        ->and((string) $sent->getBody())->toStartWith("--{$boundary}")
        ->toContain('filename="avatar.png"')
        ->toContain('png-bytes');
});
