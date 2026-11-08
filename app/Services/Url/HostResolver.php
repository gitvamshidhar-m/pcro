<?php

namespace App\Services\Url;

interface HostResolver
{
    /**
     * Resolve a hostname to all currently published IP addresses.
     *
     * @return array<int, string>
     */
    public function resolve(string $host): array;
}
