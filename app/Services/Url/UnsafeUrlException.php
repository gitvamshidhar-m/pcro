<?php

namespace App\Services\Url;

use RuntimeException;

class UnsafeUrlException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $publicMessage,
    ) {
        parent::__construct($reason);
    }

    public static function blocked(string $reason, string $publicMessage): self
    {
        return new self($reason, $publicMessage);
    }
}
