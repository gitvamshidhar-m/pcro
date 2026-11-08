import http from 'node:http';
import net from 'node:net';
import { parseTarget, resolveSafeTarget } from './url-safety.js';

const ALLOWED_PORTS = new Set([80, 443]);

function splitHostPort(value) {
    const input = String(value ?? '').trim();

    if (input.startsWith('[')) {
        const end = input.indexOf(']');
        const host = input.slice(1, end);
        const port = input.slice(end + 1).replace(/^:/, '');
        return [host, port];
    }

    const separator = input.lastIndexOf(':');

    if (separator === -1) {
        return [input, ''];
    }

    return [input.slice(0, separator), input.slice(separator + 1)];
}

/**
 * A local forward proxy that pins every connection to a validated, public IP.
 *
 * Chromium is forced through this proxy. Because the proxy performs DNS
 * resolution itself and connects to the resolved address, a DNS-rebinding
 * response cannot redirect the browser to a private network after validation.
 */
export async function createSafeProxy({ logger = () => {} } = {}) {
    const blocked = [];

    const recordBlocked = (target, error) => {
        const entry = {
            target: String(target ?? '').slice(0, 500),
            code: error?.code ?? 'blocked',
            message: error?.message ?? 'Blocked by proxy',
            at: new Date().toISOString(),
        };

        blocked.push(entry);
        if (blocked.length > 100) {
            blocked.shift();
        }

        logger(`proxy blocked ${entry.target}: ${entry.message}`);
    };

    const server = http.createServer(async (request, response) => {
        try {
            if (!request.url || !/^https?:\/\//i.test(request.url)) {
                throw Object.assign(new Error('The proxy requires an absolute http(s) URL.'), { code: 'malformed_url' });
            }

            const { url, hostname, port } = parseTarget(request.url);

            if (!ALLOWED_PORTS.has(port)) {
                throw Object.assign(new Error('Only ports 80 and 443 are allowed.'), { code: 'blocked_port' });
            }

            const target = await resolveSafeTarget(hostname);
            const headers = { ...request.headers, host: url.host };

            delete headers['proxy-connection'];
            delete headers['proxy-authorization'];

            const upstream = http.request({
                host: target.address,
                port,
                method: request.method,
                path: `${url.pathname}${url.search}`,
                headers,
                family: target.family,
                setHost: false,
            }, (upstreamResponse) => {
                response.writeHead(upstreamResponse.statusCode ?? 502, upstreamResponse.headers);
                upstreamResponse.pipe(response);
            });

            upstream.setTimeout(120_000, () => upstream.destroy(new Error('Upstream timeout')));
            upstream.on('error', (error) => {
                recordBlocked(url.href, error);
                if (!response.headersSent) {
                    response.writeHead(502, { 'content-type': 'text/plain' });
                }
                response.end('Upstream request failed');
            });

            request.pipe(upstream);
        } catch (error) {
            recordBlocked(request.url, error);

            if (!response.headersSent) {
                response.writeHead(403, { 'content-type': 'text/plain' });
            }

            response.end(`Blocked: ${error.message}`);
        }
    });

    server.on('connect', async (request, clientSocket, head) => {
        clientSocket.on('error', () => {});

        try {
            const [rawHost, rawPort] = splitHostPort(request.url);
            const port = Number(rawPort || 443);

            if (!ALLOWED_PORTS.has(port)) {
                throw Object.assign(new Error('Only ports 80 and 443 are allowed.'), { code: 'blocked_port' });
            }

            const target = await resolveSafeTarget(rawHost);

            const upstream = net.connect({
                host: target.address,
                port,
                family: target.family,
            }, () => {
                clientSocket.write('HTTP/1.1 200 Connection Established\r\nProxy-Agent: PCRO-SafeProxy\r\n\r\n');

                if (head && head.length > 0) {
                    upstream.write(head);
                }

                upstream.pipe(clientSocket);
                clientSocket.pipe(upstream);
            });

            upstream.setTimeout(120_000, () => upstream.destroy(new Error('Upstream timeout')));
            upstream.on('error', (error) => {
                recordBlocked(`${rawHost}:${port}`, error);
                clientSocket.destroy();
            });
            clientSocket.on('close', () => upstream.destroy());
        } catch (error) {
            recordBlocked(request.url, error);
            clientSocket.end('HTTP/1.1 403 Forbidden\r\nContent-Type: text/plain\r\n\r\nBlocked by SSRF guard');
        }
    });

    server.on('clientError', (error, socket) => {
        if (socket.writable) {
            socket.end('HTTP/1.1 400 Bad Request\r\n\r\n');
        }
        socket.destroy();
    });

    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', resolve);
    });

    return {
        server,
        port: server.address().port,
        getBlocked: () => [...blocked],
        close: () => new Promise((resolve) => server.close(resolve)),
    };
}
