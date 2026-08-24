#!/usr/bin/env php
<?php

declare(strict_types=1);

use Formable\Formable;
use Formable\FormableError;

$autoload = dirname(__DIR__).'/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Run `composer install` in formable-php first.\n");
    exit(1);
}

require $autoload;

$apiKey = getenv('FORMABLE_API_KEY') ?: '';
if ($apiKey === '') {
    fwrite(STDERR, "Set FORMABLE_API_KEY (from https://app.formabledocs.com/settings).\n");
    exit(1);
}

$baseUrl = getenv('FORMABLE_BASE_URL') ?: null;
$file = $argv[1] ?? null;
$signerEmail = getenv('FORMABLE_SIGNER_EMAIL') ?: 'signer@example.com';

$formable = new Formable($apiKey, $baseUrl);

try {
    dumpJson('health', $formable->health());
    dumpJson('billing', $formable->billing());
    dumpJson('signature requests', $formable->signatureRequests->list());
    dumpJson('redline requests', $formable->redlineRequests->list());

    if ($file === null) {
        fwrite(STDERR, "Pass a PDF, DOC, or DOCX to upload a template and create a test signature request:\n");
        fwrite(STDERR, "  php examples/quickstart.php ./agreement.pdf\n");
        exit(0);
    }

    if (!is_file($file)) {
        fwrite(STDERR, "File not found: {$file}\n");
        exit(1);
    }

    $filename = basename($file);
    $template = $formable->templates->create(
        file: $file,
        filename: $filename,
        signerRoles: [['name' => 'Client', 'order' => 0]],
    );
    dumpJson('template', $template);

    $templateId = $template['templateId'];
    $edit = $formable->templates->createEditUrl($templateId);
    dumpJson('edit url', $edit);

    echo "Open the edit URL, place a Signature field for the Client role, then save the template.\n";
    echo "Press Enter to create a test-mode embedded signature request...\n";
    fgets(STDIN);

    $embedded = $formable->signatureRequests->createEmbedded(
        templateId: $templateId,
        signers: [['email' => $signerEmail, 'name' => 'Jane Doe', 'role' => 'Client']],
        testMode: true,
    );
    dumpJson('embedded signature request', $embedded);

    $recipientSignatureId = $embedded['signers'][0]['recipientSignatureId'] ?? null;
    if (!is_string($recipientSignatureId) || $recipientSignatureId === '') {
        fwrite(STDERR, "No recipientSignatureId in createEmbedded response.\n");
        exit(1);
    }

    $signing = $formable->signatureRequests->createSigningUrl($recipientSignatureId);
    dumpJson('signing url', $signing);

    $current = $formable->signatureRequests->get($embedded['signatureRequestId']);
    dumpJson('signature request', $current);
} catch (FormableError $error) {
    fwrite(STDERR, $error->status.' '.$error->getMessage()."\n");
    exit(1);
}

function dumpJson(string $label, mixed $value): void
{
    echo "=== {$label} ===\n";
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n";
}
