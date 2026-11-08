<?php

namespace App\Exceptions;

use RuntimeException;

class CrawlFailedException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $publicMessage,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromWorkerError(string $message, array $context = []): self
    {
        return new self($message, $message, $context);
    }
}
