# formable-php

Official PHP SDK for the [Formable API](https://api.formabledocs.com) (v1). Covers templates, signature requests, redlining, and billing.

- Guzzle HTTP client (injectable)
- PHP 8.1+

## Installation

```bash
composer require formable/formable
```

## Usage

```php
use Formable\Formable;

$formable = new Formable(getenv('FORMABLE_API_KEY'));
```

### Templates

```php
$result = $formable->templates->create(
    file: file_get_contents('./nda.docx'),
    filename: 'nda.docx',
    signerRoles: [
        ['name' => 'Client', 'order' => 0],
        ['name' => 'Witness', 'order' => 1],
    ],
);

$templateId = $result['templateId'];

// Mint a fresh edit URL later (expires after 1 day)
$edit = $formable->templates->createEditUrl($templateId);
```

### Signature requests

```php
// Formable emails each signer a signing link
$request = $formable->signatureRequests->create(
    templateId: $templateId,
    signers: [
        ['email' => 'jane@example.com', 'name' => 'Jane Doe', 'role' => 'Client'],
        ['email' => 'bob@example.com', 'name' => 'Bob Smith', 'role' => 'Witness'],
    ],
);

// Embedded flow: mint signing URLs to embed in an iframe yourself
$embedded = $formable->signatureRequests->createEmbedded(
    templateId: $templateId,
    signers: [['email' => 'jane@example.com', 'name' => 'Jane Doe', 'role' => 'Client']],
    testMode: true,
);

$signer = $embedded['signers'][0];
$signing = $formable->signatureRequests->createSigningUrl(
    $signer['recipientSignatureId']
);

// Track progress
$current = $formable->signatureRequests->get($embedded['signatureRequestId']);
$all = $formable->signatureRequests->list(
    updatedSince: new DateTimeImmutable('2026-01-01T00:00:00Z')
);
$events = $formable->signatureRequests->getEvents($embedded['signatureRequestId']);

// Download the signed document once completed
$envelope = $formable->signatureRequests->getSignedEnvelope(
    $embedded['signatureRequestId']
);
```

### Redline requests

```php
$created = $formable->redlineRequests->create(
    templateId: $templateId,
    members: [
        ['email' => 'us@example.com', 'displayName' => 'John Doe', 'role' => 'DisclosingParty'],
        ['email' => 'them@example.com', 'displayName' => 'Jane Smith', 'role' => 'ReceivingParty'],
    ],
    metadata: ['subject' => 'Mutual NDA'],
);

$redlineRequestId = $created['redlineRequestId'];

// Mint a redline URL for a member (embed in an iframe)
$url = $formable->redlineRequests->createUrl($redlineRequestId, 'them@example.com');

// Manage members and track progress
$formable->redlineRequests->updateMembers($redlineRequestId, [
    ['email' => 'counsel@example.com', 'displayName' => 'Counsel', 'role' => 'ReceivingCounsel'],
]);
$redline = $formable->redlineRequests->get($redlineRequestId);
$events = $formable->redlineRequests->getEvents($redlineRequestId);
```

### Billing and health

```php
$billing = $formable->billing();
$health = $formable->health();
```

## Error handling

All non-2xx responses throw a `FormableError` with the server's error message, HTTP status, and parsed response body.

```php
use Formable\FormableError;

try {
    $formable->signatureRequests->get('missing-id');
} catch (FormableError $error) {
    error_log($error->status.' '.$error->getMessage());
}
```

## Configuration

| Option    | Description                                               | Default                           |
| --------- | --------------------------------------------------------- | --------------------------------- |
| `apiKey`  | Your Formable API key (sent as a bearer token). Required. | -                                 |
| `baseUrl` | Override the API base URL.                                | `https://api.formabledocs.com/v1` |
| `client`  | Custom `GuzzleHttp\ClientInterface`.                      | Built-in client with 60s timeout  |

## Development

```bash
composer install
composer test
```

To publish a new version, see [RELEASING.md](RELEASING.md).
