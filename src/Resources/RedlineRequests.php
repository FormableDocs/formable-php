<?php

declare(strict_types=1);

namespace Formable\Resources;

use DateTimeInterface;
use Formable\Internal\HttpClient;
use Formable\Internal\Params;

final class RedlineRequests
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function create(
        string $templateId,
        array $members,
        ?bool $testMode = null,
        ?array $metadata = null,
    ): array {
        return $this->http->post('/redline-requests', Params::dropNone([
            'templateId' => $templateId,
            'members' => Params::toApiMembers($members),
            'testMode' => $testMode,
            'metadata' => $metadata,
        ]));
    }

    public function list(string|DateTimeInterface|null $updatedSince = null): array
    {
        return $this->http->get('/redline-requests', [
            'updatedSince' => Params::updatedSince($updatedSince),
        ]);
    }

    public function get(string $redlineRequestId): array
    {
        return $this->http->get(self::path($redlineRequestId));
    }

    public function updateMembers(string $redlineRequestId, array $members): array
    {
        return $this->http->put(self::path($redlineRequestId, '/members'), [
            'members' => Params::toApiMembers($members),
        ]);
    }

    public function createUrl(string $redlineRequestId, string $memberEmail): array
    {
        return $this->http->post(self::path($redlineRequestId, '/url'), [
            'memberEmail' => $memberEmail,
        ]);
    }

    public function getEvents(string $redlineRequestId): array
    {
        return $this->http->get(self::path($redlineRequestId, '/events'));
    }

    private static function path(string $redlineRequestId, string $suffix = ''): string
    {
        return '/redline-requests/'.Params::encodePath($redlineRequestId).$suffix;
    }
}
