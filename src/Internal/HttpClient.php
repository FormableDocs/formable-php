<?php

declare(strict_types=1);

namespace Formable\Internal;

use Formable\FormableError;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpClient
{
    public const DEFAULT_BASE_URL = 'https://api.formabledocs.com/v1';

    private ClientInterface $client;
    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        string $apiKey,
        ?string $baseUrl = null,
        ?ClientInterface $client = null,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Formable API key is required');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->client = $client ?? new Client([
            'timeout' => 60,
            'http_errors' => false,
        ]);
    }

    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, query: $query);
    }

    public function post(string $path, ?array $body = null): mixed
    {
        return $this->request('POST', $path, body: $body);
    }

    public function put(string $path, ?array $body = null): mixed
    {
        return $this->request('PUT', $path, body: $body);
    }

    public function postForm(string $path, array $multipart): mixed
    {
        return $this->request('POST', $path, multipart: $multipart);
    }

    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?array $multipart = null,
    ): mixed {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
            'query' => Params::dropNone($query),
        ];

        if ($multipart !== null) {
            $options['multipart'] = $multipart;
        } elseif ($body !== null) {
            $options['json'] = $body;
        }

        return $this->handle(
            $this->client->request($method, $this->baseUrl.$path, $options),
        );
    }

    private function handle(ResponseInterface $response): mixed
    {
        $text = (string) $response->getBody();
        $data = $this->parseBody($text);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return $data;
        }

        $message = is_array($data) && isset($data['error']) && is_string($data['error'])
            ? $data['error']
            : "Request failed with status {$status}";

        throw new FormableError($message, $status, $data);
    }

    private function parseBody(string $text): mixed
    {
        if ($text === '') {
            return null;
        }

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $text;
        }
    }
}
