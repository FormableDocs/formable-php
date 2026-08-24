<?php

declare(strict_types=1);

namespace Formable;

use RuntimeException;

class FormableError extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly mixed $body = null,
    ) {
        parent::__construct($message, $status);
    }
}
