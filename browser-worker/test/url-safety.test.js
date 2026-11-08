import assert from 'node:assert/strict';
import test from 'node:test';
import {
    assertHostnameAllowed,
    isPublicIp,
    normaliseHostname,
    parseTarget,
    resolveSafeTarget,
    UnsafeTargetError,
} from '../src/url-safety.js';

test('normalises hostnames', () => {
    assert.equal(normaliseHostname('  Example.COM. '), 'example.com');
    assert.equal(normaliseHostname('[::1]'), '::1');
});

test('detects public and private addresses', () => {
    for (const ip of ['93.184.216.34', '8.8.8.8', '2606:4700:4700::1111']) {
        assert.equal(isPublicIp(ip), true, `${ip} should be public`);
    }

    for (const ip of ['127.0.0.1', '10.0.0.1', '172.16.5.4', '192.168.1.1', '169.254.169.254', '::1', 'fc00::1', 'fe80::1', 'not-an-ip']) {
        assert.equal(isPublicIp(ip), false, `${ip} should be blocked`);
    }
});

test('rejects internal hostnames', () => {
    for (const host of ['localhost', 'metadata.google.internal', 'app.internal', 'intranet', 'server.local', 'thing.arpa']) {
        assert.throws(() => assertHostnameAllowed(host), UnsafeTargetError, `${host} should be rejected`);
    }

    assert.doesNotThrow(() => assertHostnameAllowed('example.com'));
    assert.doesNotThrow(() => assertHostnameAllowed('93.184.216.34'));
});

test('rejects private IP literals before resolving', async () => {
    await assert.rejects(() => resolveSafeTarget('10.0.0.4'), UnsafeTargetError);
    await assert.rejects(() => resolveSafeTarget('169.254.169.254'), UnsafeTargetError);
});

test('parses targets with scheme, port and credential checks', () => {
    const httpTarget = parseTarget('http://example.com:80/path');
    assert.equal(httpTarget.hostname, 'example.com');
    assert.equal(httpTarget.port, 80);

    const httpsTarget = parseTarget('https://example.com');
    assert.equal(httpsTarget.port, 443);

    assert.throws(() => parseTarget('ftp://example.com'), UnsafeTargetError);
    assert.throws(() => parseTarget('https://example.com:8443'), UnsafeTargetError);
    assert.throws(() => parseTarget('https://user:pass@example.com'), UnsafeTargetError);
    assert.throws(() => parseTarget('not a url'), UnsafeTargetError);
});
