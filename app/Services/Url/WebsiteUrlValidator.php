<?php

namespace App\Services\Url;

use Illuminate\Support\Str;

class WebsiteUrlValidator
{
    private const MAX_URL_LENGTH = 2048;

    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443];

    /**
     * @var array<int, string>
     */
    private const BLOCKED_HOST_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.lan',
        '.home',
        '.arpa',
        '.test',
        '.invalid',
        '.onion',
    ];

    /**
     * @var array<int, string>
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'metadata',
        'metadata.google.internal',
        'instance-data',
        'kubernetes.default',
        'kubernetes.default.svc',
    ];

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly PublicIpGuard $ipGuard,
    ) {}

    public function validate(string $input): ValidatedUrl
    {
        $input = trim($input);

        if ($input === '') {
            throw UnsafeUrlException::blocked('empty_url', 'Enter a website address to analyse.');
        }

        if (strlen($input) > self::MAX_URL_LENGTH) {
            throw UnsafeUrlException::blocked('url_too_long', 'The website address is too long.');
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $input) === 1) {
            throw UnsafeUrlException::blocked('invalid_characters', 'The website address contains invalid characters.');
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://'.$input;
        }

        $parts = parse_url($input);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw UnsafeUrlException::blocked('malformed_url', 'Enter a valid public website address.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw UnsafeUrlException::blocked('unsupported_scheme', 'Only http and https websites can be analysed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw UnsafeUrlException::blocked('credentials_in_url', 'Website addresses cannot contain credentials.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if ($host === '') {
            throw UnsafeUrlException::blocked('missing_host', 'Enter a valid public website address.');
        }

        if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
            $host = '['.$host.']';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw UnsafeUrlException::blocked('blocked_port', 'Only standard web ports (80 and 443) are supported.');
        }

        $bracketedIp = trim($host, '[]');
        $isIpLiteral = filter_var($bracketedIp, FILTER_VALIDATE_IP) !== false;

        if ($isIpLiteral) {
            if (! $this->ipGuard->isPublic($bracketedIp)) {
                throw UnsafeUrlException::blocked('private_ip', 'Private, local and reserved IP addresses cannot be analysed.');
            }

            $host = $bracketedIp;

            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $host = '['.$host.']';
            }

            $resolvedIps = [$bracketedIp];
        } else {
            $host = $this->normaliseHostname($host);
            $this->assertHostnameAllowed($host);
            $resolvedIps = $this->resolver->resolve($host);

            if ($resolvedIps === []) {
                throw UnsafeUrlException::blocked('dns_failure', 'The website address could not be resolved. Check the domain and try again.');
            }

            foreach ($resolvedIps as $ip) {
                if (! $this->ipGuard->isPublic($ip)) {
                    throw UnsafeUrlException::blocked('private_dns', 'This domain resolves to a private or reserved network and cannot be analysed.');
                }
            }
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $isDefaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
        $portSuffix = $isDefaultPort ? '' : ':'.$port;

        $url = $scheme.'://'.$host.$portSuffix.$path.$query;
        $domain = strtolower(preg_replace('/^www\./', '', trim($host, '[]')) ?? $host);

        return new ValidatedUrl(
            url: $url,
            scheme: $scheme,
            host: trim($host, '[]'),
            domain: $domain,
            port: $port,
            resolvedIps: $resolvedIps,
        );
    }

    private function normaliseHostname(string $host): string
    {
        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                return strtolower($ascii);
            }
        }

        return $host;
    }

    private function assertHostnameAllowed(string $host): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            throw UnsafeUrlException::blocked('blocked_host', 'Local and internal hostnames cannot be analysed.');
        }

        if (! str_contains($host, '.')) {
            throw UnsafeUrlException::blocked('single_label_host', 'Enter a public domain name such as example.com.');
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if (Str::endsWith($host, $suffix)) {
                throw UnsafeUrlException::blocked('blocked_host_suffix', 'Local, internal and reserved hostnames cannot be analysed.');
            }
        }

        if (preg_match('/^[0-9.]+$/', $host) === 1) {
            throw UnsafeUrlException::blocked('invalid_host', 'Enter a valid public website address.');
        }

        if (! preg_match('/^[a-z0-9._-]+$/', $host)) {
            throw UnsafeUrlException::blocked('invalid_host', 'Enter a valid public website address.');
        }
    }
}
