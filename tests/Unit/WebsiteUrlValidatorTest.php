<?php

namespace Tests\Unit;

use App\Services\Url\HostResolver;
use App\Services\Url\PublicIpGuard;
use App\Services\Url\UnsafeUrlException;
use App\Services\Url\WebsiteUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebsiteUrlValidatorTest extends TestCase
{
    private PublicIpGuard $ipGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ipGuard = new PublicIpGuard;
    }

    public function test_it_normalises_a_bare_domain_to_https(): void
    {
        $validator = $this->validatorWith(['example.com' => ['93.184.216.34']]);

        $validated = $validator->validate('Example.COM/pricing?plan=pro#section');

        $this->assertSame('https://example.com/pricing?plan=pro', $validated->url);
        $this->assertSame('example.com', $validated->host);
        $this->assertSame('example.com', $validated->domain);
        $this->assertSame(443, $validated->port);
        $this->assertSame(['93.184.216.34'], $validated->resolvedIps);
    }

    public function test_it_accepts_http_and_preserves_www_domain_separately(): void
    {
        $validator = $this->validatorWith(['www.example.com' => ['93.184.216.34']]);

        $validated = $validator->validate('http://www.example.com');

        $this->assertSame('http://www.example.com/', $validated->url);
        $this->assertSame('www.example.com', $validated->host);
        $this->assertSame('example.com', $validated->domain);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'localhost' => ['http://localhost'],
            'loopback' => ['http://127.0.0.1'],
            'private class A' => ['http://10.0.0.4'],
            'private class B' => ['http://172.16.10.10'],
            'private class C' => ['http://192.168.1.5'],
            'link local metadata' => ['http://169.254.169.254/latest/meta-data'],
            'ipv6 loopback' => ['http://[::1]'],
            'ipv6 mapped loopback' => ['http://[::ffff:127.0.0.1]'],
            'credentials' => ['https://user:pass@example.com'],
            'ftp scheme' => ['ftp://example.com'],
            'file scheme' => ['file:///etc/passwd'],
            'non standard port' => ['https://example.com:8443'],
            'internal suffix' => ['http://app.internal'],
            'single label' => ['http://intranet'],
            'empty' => [''],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_it_rejects_unsafe_urls(string $url): void
    {
        $validator = $this->validatorWith(['example.com' => ['93.184.216.34'], 'app.internal' => ['10.0.0.1'], 'intranet' => ['10.0.0.2']]);

        $this->expectException(UnsafeUrlException::class);
        $validator->validate($url);
    }

    public function test_it_blocks_hostnames_resolving_to_private_ips(): void
    {
        $validator = $this->validatorWith(['evil.example' => ['10.1.2.3']]);

        $this->expectException(UnsafeUrlException::class);
        $validator->validate('https://evil.example');
    }

    public function test_it_blocks_mixed_public_and_private_dns_answers(): void
    {
        $validator = $this->validatorWith(['rebind.example' => ['93.184.216.34', '127.0.0.1']]);

        $this->expectException(UnsafeUrlException::class);
        $validator->validate('https://rebind.example');
    }

    public function test_it_rejects_unresolvable_domains(): void
    {
        $validator = $this->validatorWith([]);

        $this->expectException(UnsafeUrlException::class);
        $validator->validate('https://does-not-resolve.example');
    }

    public function test_public_ip_guard_blocks_non_public_ranges(): void
    {
        foreach (['127.0.0.1', '10.0.0.1', '100.64.0.1', '169.254.169.254', '192.168.0.1', '224.0.0.1', '::1', 'fc00::1', 'fe80::1'] as $ip) {
            $this->assertFalse($this->ipGuard->isPublic($ip), "Expected {$ip} to be blocked.");
        }

        foreach (['93.184.216.34', '8.8.8.8', '2606:4700:4700::1111'] as $ip) {
            $this->assertTrue($this->ipGuard->isPublic($ip), "Expected {$ip} to be public.");
        }
    }

    /**
     * @param  array<string, array<int, string>>  $dns
     */
    private function validatorWith(array $dns): WebsiteUrlValidator
    {
        $resolver = new class($dns) implements HostResolver
        {
            /**
             * @param  array<string, array<int, string>>  $dns
             */
            public function __construct(private array $dns) {}

            public function resolve(string $host): array
            {
                return $this->dns[$host] ?? [];
            }
        };

        return new WebsiteUrlValidator($resolver, $this->ipGuard);
    }
}
