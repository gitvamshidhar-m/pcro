import dns from 'node:dns/promises';
import net from 'node:net';
import ipaddr from 'ipaddr.js';

const BLOCKED_HOSTS = new Set([
    'localhost',
    'metadata',
    'metadata.google.internal',
    'instance-data',
    'kubernetes.default',
    'kubernetes.default.svc',
]);

const BLOCKED_SUFFIXES = [
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

const ALLOWED_RANGES = new Set(['unicast']);

const dnsCache = new Map();
const DNS_TTL_MS = 30_000;

export class UnsafeTargetError extends Error {
    constructor(code, message) {
        super(message);
        this.name = 'UnsafeTargetError';
        this.code = code;
    }
}

export function normaliseHostname(hostname) {
    return String(hostname).trim().toLowerCase().replace(/^\[|\]$/g, '').replace(/\.$/, '');
}

export function isPublicIp(ip) {
    if (!ipaddr.isValid(ip)) {
        return false;
    }

    let address = ipaddr.parse(ip);

    if (address.kind() === 'ipv6' && address.isIPv4MappedAddress()) {
        address = address.toIPv4Address();
    }

    return ALLOWED_RANGES.has(address.range());
}

export function assertHostnameAllowed(hostname) {
    const host = normaliseHostname(hostname);

    if (host === '') {
        throw new UnsafeTargetError('missing_host', 'The target has no hostname.');
    }

    if (net.isIP(host)) {
        if (!isPublicIp(host)) {
            throw new UnsafeTargetError('private_ip', 'The target resolves to a private or reserved IP address.');
        }

        return;
    }

    if (BLOCKED_HOSTS.has(host)) {
        throw new UnsafeTargetError('blocked_host', 'The target hostname is local or internal.');
    }

    if (!host.includes('.')) {
        throw new UnsafeTargetError('single_label_host', 'The target hostname is not a public domain.');
    }

    if (BLOCKED_SUFFIXES.some((suffix) => host.endsWith(suffix))) {
        throw new UnsafeTargetError('blocked_host', 'The target hostname is local or reserved.');
    }
}

/**
 * Resolve a hostname and guarantee that every published address is public.
 * Returns the pinned IP address that the connection must use.
 */
export async function resolveSafeTarget(hostname, { fresh = false } = {}) {
    const host = normaliseHostname(hostname);

    if (net.isIP(host)) {
        assertHostnameAllowed(host);
        return { host, address: host, family: net.isIP(host) };
    }

    assertHostnameAllowed(host);

    const cached = dnsCache.get(host);
    if (!fresh && cached && cached.expiresAt > Date.now()) {
        return cached.result;
    }

    let records;
    try {
        records = await dns.lookup(host, { all: true, verbatim: true });
    } catch {
        throw new UnsafeTargetError('dns_failure', `The hostname ${host} could not be resolved.`);
    }

    if (!Array.isArray(records) || records.length === 0) {
        throw new UnsafeTargetError('dns_failure', `The hostname ${host} could not be resolved.`);
    }

    for (const record of records) {
        if (!isPublicIp(record.address)) {
            throw new UnsafeTargetError('private_dns', `The hostname ${host} resolves to a private or reserved address.`);
        }
    }

    const chosen = records.find((record) => record.family === 4) ?? records[0];
    const result = { host, address: chosen.address, family: chosen.family, all: records.map((record) => record.address) };
    dnsCache.set(host, { result, expiresAt: Date.now() + DNS_TTL_MS });

    return result;
}

export function parseTarget(input) {
    let url;

    try {
        url = new URL(input);
    } catch {
        throw new UnsafeTargetError('malformed_url', 'The target URL is malformed.');
    }

    if (!['http:', 'https:'].includes(url.protocol)) {
        throw new UnsafeTargetError('unsupported_scheme', 'Only http and https targets are supported.');
    }

    if (url.username || url.password) {
        throw new UnsafeTargetError('credentials_in_url', 'Target URLs cannot contain credentials.');
    }

    const port = Number(url.port || (url.protocol === 'https:' ? 443 : 80));

    if (![80, 443].includes(port)) {
        throw new UnsafeTargetError('blocked_port', 'Only ports 80 and 443 are allowed.');
    }

    return { url, hostname: normaliseHostname(url.hostname), port };
}

export function clearDnsCache() {
    dnsCache.clear();
}
