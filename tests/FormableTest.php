<?php

declare(strict_types=1);

namespace Formable\Tests;

use ArrayObject;
use DateTimeImmutable;
use Formable\Formable;
use Formable\FormableError;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class FormableTest extends TestCase
{
    public function testRequiresApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Formable('');
    }

    public function testSendsBearerTokenAndBaseUrl(): void
    {
        [$client, $history] = $this->makeClient(['status' => 'healthy']);
        $formable = new Formable('test-key', client: $client);

        $this->assertSame(['status' => 'healthy'], $formable->health());
        $request = $this->lastRequest($history);
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertSame(Formable::DEFAULT_BASE_URL.'/health', (string) $request->getUri());
    }

    public function testErrorRaisesFormableError(): void
    {
        [$client] = $this->makeClient(
            ['error' => 'Template not found for id abc'],
            404,
        );
        $formable = new Formable('test-key', client: $client);

        try {
            $formable->signatureRequests->get('abc');
            $this->fail('Expected FormableError');
        } catch (FormableError $error) {
            $this->assertSame(404, $error->status);
            $this->assertSame('Template not found for id abc', $error->getMessage());
            $this->assertSame(['error' => 'Template not found for id abc'], $error->body);
        }
    }

    public function testCreateTemplateMultipart(): void
    {
        [$client, $history] = $this->makeClient(['templateId' => 'tmpl_1']);
        $formable = new Formable('test-key', client: $client);

        $result = $formable->templates->create(
            file: 'file-bytes',
            filename: 'nda.docx',
            signerRoles: [['name' => 'Client', 'order' => 0]],
        );

        $this->assertSame(['templateId' => 'tmpl_1'], $result);
        $request = $this->lastRequest($history);
        $this->assertSame('/v1/templates', $request->getUri()->getPath());
        $this->assertStringStartsWith('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        $this->assertStringContainsString('name="filename"', $body);
        $this->assertStringContainsString('nda.docx', $body);
        $this->assertStringContainsString('[{"name":"Client","order":0}]', $body);
    }

    public function testCreateSignatureRequestBody(): void
    {
        [$client, $history] = $this->makeClient(['signatureRequestId' => 'sr_1']);
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->create(
            templateId: 'tmpl_1',
            signers: [['email' => 'jane@example.com', 'name' => 'Jane', 'role' => 'Client']],
            testMode: true,
            fields: [['field_id' => 'field_1', 'value' => 'hello']],
        );

        $this->assertSame(
            [
                'templateId' => 'tmpl_1',
                'signers' => [
                    ['email' => 'jane@example.com', 'name' => 'Jane', 'role' => 'Client'],
                ],
                'testMode' => true,
                'fields' => [['fieldId' => 'field_1', 'value' => 'hello']],
            ],
            $this->jsonBody($history),
        );
    }

    public function testCreateEmbeddedSignatureRequest(): void
    {
        [$client, $history] = $this->makeClient(['signatureRequestId' => 'sr_1']);
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->createEmbedded(
            templateId: 'tmpl_1',
            signers: [['email' => 'jane@example.com', 'name' => 'Jane']],
        );

        $request = $this->lastRequest($history);
        $this->assertSame('/v1/signature-requests/embedded', $request->getUri()->getPath());
        $this->assertSame(
            [
                'templateId' => 'tmpl_1',
                'signers' => [['email' => 'jane@example.com', 'name' => 'Jane']],
            ],
            $this->jsonBody($history),
        );
    }

    public function testListWithUpdatedSinceDateTime(): void
    {
        [$client, $history] = $this->makeClient([]);
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->list(
            updatedSince: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );

        parse_str($this->lastRequest($history)->getUri()->getQuery(), $query);
        $this->assertSame('2026-01-01T00:00:00+00:00', $query['updatedSince']);
    }

    public function testListWithoutUpdatedSinceOmitsParam(): void
    {
        [$client, $history] = $this->makeClient([]);
        $formable = new Formable('test-key', client: $client);

        $formable->redlineRequests->list();

        $this->assertSame('', $this->lastRequest($history)->getUri()->getQuery());
    }

    public function testCreateRedlineRequestConvertsMembers(): void
    {
        [$client, $history] = $this->makeClient([
            'redlineRequestId' => 'rr_1',
            'templateId' => 'tmpl_2',
        ]);
        $formable = new Formable('test-key', client: $client);

        $formable->redlineRequests->create(
            templateId: 'tmpl_1',
            members: [
                [
                    'email' => 'a@example.com',
                    'display_name' => 'A',
                    'role' => 'DisclosingParty',
                ],
            ],
            metadata: ['subject' => 'Mutual NDA'],
        );

        $this->assertSame(
            [
                'templateId' => 'tmpl_1',
                'members' => [
                    [
                        'email' => 'a@example.com',
                        'displayName' => 'A',
                        'role' => 'DisclosingParty',
                    ],
                ],
                'metadata' => ['subject' => 'Mutual NDA'],
            ],
            $this->jsonBody($history),
        );
    }

    public function testPathParamsAreEncoded(): void
    {
        [$client, $history] = $this->makeClient(['members' => []]);
        $formable = new Formable('test-key', client: $client);

        $formable->redlineRequests->updateMembers(
            'id/with slash',
            [['email' => 'a@b.com', 'display_name' => 'A', 'role' => 'ReceivingParty']],
        );

        $request = $this->lastRequest($history);
        $this->assertSame('/v1/redline-requests/id%2Fwith%20slash/members', $request->getUri()->getPath());
        $this->assertSame('PUT', $request->getMethod());
    }

    public function testCreateSigningUrlPath(): void
    {
        [$client, $history] = $this->makeClient([
            'signingUrl' => 'https://example.com',
            'expiresAt' => 'soon',
        ]);
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->createSigningUrl('rsig_1');

        $request = $this->lastRequest($history);
        $this->assertSame('/v1/recipient-signatures/rsig_1/url', $request->getUri()->getPath());
        $this->assertSame('POST', $request->getMethod());
    }

    public function testCreateRedlineUrlBody(): void
    {
        [$client, $history] = $this->makeClient([
            'redlineUrl' => 'https://example.com',
            'expiresAt' => 'soon',
        ]);
        $formable = new Formable('test-key', client: $client);

        $formable->redlineRequests->createUrl('rr_1', 'member@example.com');

        $this->assertSame('/v1/redline-requests/rr_1/url', $this->lastRequest($history)->getUri()->getPath());
        $this->assertSame(['memberEmail' => 'member@example.com'], $this->jsonBody($history));
    }

    public function testGetSignedEnvelopePath(): void
    {
        [$client, $history] = $this->makeClient([
            'signedEnvelopePresignedUrl' => 'https://s3.example.com',
        ]);
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->getSignedEnvelope('sr_1');

        $this->assertSame(
            '/v1/signature-requests/sr_1/signed-envelope',
            $this->lastRequest($history)->getUri()->getPath(),
        );
    }

    public function testGetEventsPaths(): void
    {
        [$client, $history] = $this->makeClient(
            ['signatureRequestEvents' => []],
            extraResponses: [new Response(200, [], '{"redlineRequestEvents":[]}')],
        );
        $formable = new Formable('test-key', client: $client);

        $formable->signatureRequests->getEvents('sr_1');
        $formable->redlineRequests->getEvents('rr_1');

        $this->assertSame(
            '/v1/signature-requests/sr_1/events',
            $history[0]['request']->getUri()->getPath(),
        );
        $this->assertSame(
            '/v1/redline-requests/rr_1/events',
            $history[1]['request']->getUri()->getPath(),
        );
    }

    public function testBilling(): void
    {
        [$client] = $this->makeClient(['numberOfRedliningSessions' => 42]);
        $formable = new Formable('test-key', client: $client);

        $this->assertSame(['numberOfRedliningSessions' => 42], $formable->billing());
    }

    public function testCreateEditUrlPath(): void
    {
        [$client, $history] = $this->makeClient([
            'editUrl' => 'https://app.formabledocs.com/template-setup/tmpl_1',
            'expiresAt' => '2026-01-16T10:30:00.000Z',
        ]);
        $formable = new Formable('test-key', client: $client);

        $formable->templates->createEditUrl('tmpl_1');

        $request = $this->lastRequest($history);
        $this->assertSame('/v1/templates/tmpl_1/edit-url', $request->getUri()->getPath());
        $this->assertSame('POST', $request->getMethod());
    }

    /**
     * @return array{0: Client, 1: ArrayObject<int, array{request: RequestInterface}>}
     */
    private function makeClient(
        mixed $responseBody,
        int $statusCode = 200,
        array $extraResponses = [],
    ): array {
        $history = new ArrayObject();
        $mock = new MockHandler([
            new Response($statusCode, [], json_encode($responseBody, JSON_THROW_ON_ERROR)),
            ...$extraResponses,
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return [new Client(['handler' => $stack, 'http_errors' => false]), $history];
    }

    private function lastRequest(ArrayObject $history): RequestInterface
    {
        return $history[$history->count() - 1]['request'];
    }

    private function jsonBody(ArrayObject $history): array
    {
        return json_decode((string) $this->lastRequest($history)->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
