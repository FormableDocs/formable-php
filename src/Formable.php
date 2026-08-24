<?php

declare(strict_types=1);

namespace Formable;

use Formable\Internal\HttpClient;
use Formable\Resources\RedlineRequests;
use Formable\Resources\SignatureRequests;
use Formable\Resources\Templates;
use GuzzleHttp\ClientInterface;

class Formable
{
    public const DEFAULT_BASE_URL = HttpClient::DEFAULT_BASE_URL;

    public readonly Templates $templates;
    public readonly SignatureRequests $signatureRequests;
    public readonly RedlineRequests $redlineRequests;

    private HttpClient $http;

    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        ?ClientInterface $client = null,
    ) {
        $this->http = new HttpClient($apiKey, $baseUrl, $client);
        $this->templates = new Templates($this->http);
        $this->signatureRequests = new SignatureRequests($this->http);
        $this->redlineRequests = new RedlineRequests($this->http);
    }

    public function billing(): array
    {
        return $this->http->get('/billing');
    }

    public function health(): array
    {
        return $this->http->get('/health');
    }
}
