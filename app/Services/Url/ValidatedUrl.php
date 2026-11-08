<?php

namespace App\Services\Url;

final readonly class ValidatedUrl
{
    /**
     * @param  array<int, string>  $resolvedIps
     */
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public string $domain,
        public int $port,
        public array $resolvedIps,
    ) {}
}
