<?php

declare(strict_types=1);

namespace Formable\Resources;

use DateTimeInterface;
use Formable\Internal\HttpClient;
use Formable\Internal\Params;

final class SignatureRequests
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function create(
        string $templateId,
        array $signers,
        ?array $sender = null,
        ?bool $testMode = null,
        ?array $fields = null,
    ): array {
        return $this->http->post('/signature-requests', self::createBody(
            $templateId,
            $signers,
            $sender,
            $testMode,
            $fields,
        ));
    }

    public function createEmbedded(
        string $templateId,
        array $signers,
        ?array $sender = null,
        ?bool $testMode = null,
        ?array $fields = null,
    ): array {
        return $this->http->post('/signature-requests/embedded', self::createBody(
            $templateId,
            $signers,
            $sender,
            $testMode,
            $fields,
        ));
    }

    public function list(string|DateTimeInterface|null $updatedSince = null): array
    {
        return $this->http->get('/signature-requests', [
            'updatedSince' => Params::updatedSince($updatedSince),
        ]);
    }

    public function get(string $signatureRequestId): array
    {
        return $this->http->get(self::path($signatureRequestId));
    }

    public function getEvents(string $signatureRequestId): array
    {
        return $this->http->get(self::path($signatureRequestId, '/events'));
    }

    public function getSignedEnvelope(string $signatureRequestId): array
    {
        return $this->http->get(self::path($signatureRequestId, '/signed-envelope'));
    }

    public function createSigningUrl(string $recipientSignatureId): array
    {
        return $this->http->post(
            '/recipient-signatures/'.Params::encodePath($recipientSignatureId).'/url',
        );
    }

    private static function createBody(
        string $templateId,
        array $signers,
        ?array $sender,
        ?bool $testMode,
        ?array $fields,
    ): array {
        return Params::dropNone([
            'templateId' => $templateId,
            'signers' => array_values($signers),
            'sender' => $sender,
            'testMode' => $testMode,
            'fields' => Params::toApiFields($fields),
        ]);
    }

    private static function path(string $signatureRequestId, string $suffix = ''): string
    {
        return '/signature-requests/'.Params::encodePath($signatureRequestId).$suffix;
    }
}
