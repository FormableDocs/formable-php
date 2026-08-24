<?php

declare(strict_types=1);

namespace Formable\Resources;

use Formable\Internal\HttpClient;
use Formable\Internal\Params;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

final class Templates
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param string|resource|StreamInterface $file File path, raw contents, or stream
     * @param list<array{name: string, order: int}>|null $signerRoles
     */
    public function create(
        mixed $file,
        string $filename,
        ?array $signerRoles = null,
    ): array {
        $multipart = [
            [
                'name' => 'file',
                'contents' => self::normalizeFile($file),
                'filename' => $filename,
            ],
            [
                'name' => 'filename',
                'contents' => $filename,
            ],
        ];

        if ($signerRoles !== null) {
            $multipart[] = [
                'name' => 'signer_roles',
                'contents' => json_encode(array_values($signerRoles), JSON_THROW_ON_ERROR),
            ];
        }

        return $this->http->postForm('/templates', $multipart);
    }

    public function createEditUrl(string $templateId): array
    {
        return $this->http->post(
            '/templates/'.Params::encodePath($templateId).'/edit-url',
        );
    }

    private static function normalizeFile(mixed $file): mixed
    {
        if (is_string($file) && is_file($file)) {
            $stream = fopen($file, 'rb');
            if ($stream === false) {
                throw new InvalidArgumentException("Unable to read file: {$file}");
            }

            return $stream;
        }

        return $file;
    }
}
