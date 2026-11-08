#!/usr/bin/env node
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import http from 'node:http';
import path from 'node:path';
import process from 'node:process';
import { spawn } from 'node:child_process';
import { InvalidJobError, normaliseJob } from './src/job.js';

const VERSION = '1.0.0';
const MAX_BODY_BYTES = 256 * 1024;
const MAX_JOBS_RETAINED = 200;

const port = Number(process.env.PCRO_WORKER_PORT ?? 8787);
const host = process.env.PCRO_WORKER_HOST ?? '127.0.0.1';
const dataDir = path.resolve(process.env.PCRO_WORKER_DATA_DIR ?? path.join(process.cwd(), 'worker-data'));
const token = process.env.PCRO_WORKER_TOKEN ?? '';
const allowInsecure = process.env.PCRO_WORKER_ALLOW_INSECURE === '1';

if (token === '' && !allowInsecure) {
    process.stderr.write('PCRO_WORKER_TOKEN must be set. Set PCRO_WORKER_ALLOW_INSECURE=1 only for local development.\n');
    process.exit(1);
}

const jobs = new Map();
const queue = [];
const running = new Set();
let activeJobId = null;

function tokenMatches(candidate) {
    if (token === '') {
        return true;
    }

    const expected = Buffer.from(token);
    const provided = Buffer.from(String(candidate ?? ''));

    if (expected.length !== provided.length) {
        return false;
    }

    return crypto.timingSafeEqual(expected, provided);
}

function authenticated(request) {
    const header = request.headers.authorization ?? '';
    const [scheme, value] = header.split(' ');

    return scheme === 'Bearer' && tokenMatches(value);
}

function sendJson(response, status, payload) {
    const body = JSON.stringify(payload);
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'content-length': Buffer.byteLength(body),
        'cache-control': 'no-store',
        'x-content-type-options': 'nosniff',
    });
    response.end(body);
}

async function readBody(request) {
    const chunks = [];
    let size = 0;

    for await (const chunk of request) {
        size += chunk.length;

        if (size > MAX_BODY_BYTES) {
            throw Object.assign(new Error('Request body is too large.'), { status: 413, code: 'body_too_large' });
        }

        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}

function jobDir(id) {
    return path.join(dataDir, 'jobs', id);
}

async function readJobFile(id, name) {
    try {
        return JSON.parse(await fs.readFile(path.join(jobDir(id), name), 'utf8'));
    } catch {
        return null;
    }
}

async function writeJobFile(id, name, payload) {
    await fs.mkdir(jobDir(id), { recursive: true });
    await fs.writeFile(path.join(jobDir(id), name), JSON.stringify(payload), 'utf8');
}

async function persistStatus(record) {
    await writeJobFile(record.id, 'status.json', publicJob(record)).catch(() => {});
}

function publicJob(record) {
    return {
        id: record.id,
        status: record.status,
        created_at: record.created_at,
        started_at: record.started_at,
        finished_at: record.finished_at,
        url: record.job?.url ?? null,
        pages_crawled: record.pages_crawled ?? null,
        pages_failed: record.pages_failed ?? null,
        error: record.error ?? null,
    };
}

function pruneJobs() {
    if (jobs.size <= MAX_JOBS_RETAINED) {
        return;
    }

    const finished = [...jobs.values()]
        .filter((record) => ['completed', 'failed', 'cancelled'].includes(record.status))
        .sort((a, b) => String(a.finished_at).localeCompare(String(b.finished_at)));

    while (jobs.size > MAX_JOBS_RETAINED && finished.length > 0) {
        const record = finished.shift();
        jobs.delete(record.id);
    }
}

async function runJob(id) {
    const record = jobs.get(id);

    if (!record || record.status === 'cancelled') {
        return;
    }

    record.status = 'running';
    record.started_at = new Date().toISOString();
    activeJobId = id;
    await persistStatus(record);

    const jobFile = path.join(jobDir(id), 'job.json');
    const output = await fs.open(path.join(jobDir(id), 'worker.log'), 'w');
    const stdoutStream = output.createWriteStream();

    const exit = await new Promise((resolve) => {
        const child = spawn(process.execPath, [path.resolve('index.js'), '--input', jobFile], {
            cwd: path.resolve('.'),
            env: { ...process.env },
            windowsHide: true,
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        record.pid = child.pid;

        child.stdout.on('data', (chunk) => stdoutStream.write(chunk));
        child.stderr.on('data', (chunk) => stdoutStream.write(chunk));
        child.on('error', (error) => resolve({ code: -1, signal: null, error }));
        child.on('close', (code, signal) => resolve({ code, signal }));
    });

    stdoutStream.end();
    await output.close().catch(() => {});
    activeJobId = null;
    record.finished_at = new Date().toISOString();

    const result = await readJobFile(id, 'result.json');
    const failure = await readJobFile(id, 'error.json');

    if (exit.code === 0 && result) {
        record.status = 'completed';
        record.pages_crawled = result.stats?.pages_crawled ?? null;
        record.pages_failed = result.stats?.pages_failed ?? null;
        record.error = null;
    } else if (record.status === 'cancelled') {
        record.error = 'Cancelled by the operator.';
    } else {
        record.status = 'failed';
        record.error = failure?.error
            ? String(failure.error).slice(0, 2000)
            : `The worker exited with code ${exit.code ?? 'unknown'}.`;
    }

    await persistStatus(record);
}

async function drainQueue() {
    if (running.size > 0) {
        return;
    }

    const id = queue.shift();

    if (!id) {
        return;
    }

    const record = jobs.get(id);

    if (!record || record.status !== 'queued') {
        await drainQueue();
        return;
    }

    running.add(id);

    try {
        await runJob(id);
    } catch (error) {
        record.status = 'failed';
        record.error = error.message;
        record.finished_at = new Date().toISOString();
        await persistStatus(record).catch(() => {});
    } finally {
        running.delete(id);
        pruneJobs();
        drainQueue().catch(() => {});
    }
}

async function createJob(request, response) {
    let payload;

    try {
        payload = JSON.parse(await readBody(request));
    } catch (error) {
        return sendJson(response, error.status ?? 400, {
            ok: false,
            code: error.code ?? 'invalid_json',
            error: error.message ?? 'The request body must be valid JSON.',
        });
    }

    if (!payload || typeof payload !== 'object') {
        return sendJson(response, 422, { ok: false, code: 'invalid_job', error: 'The request body must be a JSON object.' });
    }

    const id = crypto.randomUUID();
    const directory = jobDir(id);

    let job;

    try {
        job = normaliseJob({
            ...payload,
            job_id: id,
            output_dir: directory,
        }, { baseDir: dataDir });
    } catch (error) {
        if (error instanceof InvalidJobError) {
            return sendJson(response, 422, { ok: false, code: error.code, error: error.message, field: error.field ?? null });
        }

        throw error;
    }

    const record = {
        id,
        status: 'queued',
        created_at: new Date().toISOString(),
        started_at: null,
        finished_at: null,
        job,
        error: null,
    };

    jobs.set(id, record);
    await writeJobFile(id, 'job.json', job);
    await persistStatus(record);
    queue.push(id);
    sendJson(response, 202, { ok: true, job: publicJob(record) });
    drainQueue().catch(() => {});
}

async function handle(request, response) {
    const url = new URL(request.url ?? '/', `http://${request.headers.host ?? 'localhost'}`);

    if (request.method === 'GET' && url.pathname === '/health') {
        return sendJson(response, 200, {
            ok: true,
            service: 'pcro-browser-worker',
            version: VERSION,
            busy: activeJobId !== null,
            active_job: activeJobId,
            queued: queue.length,
            completed_jobs: [...jobs.values()].filter((record) => record.status === 'completed').length,
            uptime_seconds: Math.round(process.uptime()),
        });
    }

    if (!authenticated(request)) {
        return sendJson(response, 401, { ok: false, code: 'unauthorized', error: 'A valid bearer token is required.' });
    }

    if (request.method === 'POST' && url.pathname === '/v1/jobs') {
        return createJob(request, response);
    }

    const match = url.pathname.match(/^\/v1\/jobs\/([a-f0-9-]{36})(?:\/(result|cancel))?$/i);

    if (!match) {
        return sendJson(response, 404, { ok: false, code: 'not_found', error: 'Unknown endpoint.' });
    }

    const [, id, action] = match;
    const record = jobs.get(id) ?? await readJobFile(id, 'status.json');

    if (!record) {
        return sendJson(response, 404, { ok: false, code: 'job_not_found', error: 'The requested job does not exist.' });
    }

    if (request.method === 'GET' && action === 'result') {
        if (record.status !== 'completed') {
            return sendJson(response, 409, { ok: false, code: 'job_not_ready', error: 'The job has not completed yet.', job: publicJob(record) });
        }

        const result = await readJobFile(id, 'result.json');

        if (!result) {
            return sendJson(response, 404, { ok: false, code: 'result_missing', error: 'The result file is missing.' });
        }

        return sendJson(response, 200, { ok: true, job: publicJob(record), result });
    }

    if (request.method === 'POST' && action === 'cancel') {
        const current = jobs.get(id);
        const index = queue.indexOf(id);

        if (index !== -1) {
            queue.splice(index, 1);
        }

        if (current) {
            current.status = 'cancelled';
            current.finished_at = new Date().toISOString();
            current.error = 'Cancelled by the operator.';

            if (current.pid) {
                try {
                    process.kill(current.pid);
                } catch {
                    // The process may already have exited.
                }
            }

            await persistStatus(current);
        }

        return sendJson(response, 200, { ok: true, job: publicJob(current ?? record) });
    }

    if (request.method === 'GET' && action === undefined) {
        const progress = await readJobFile(id, 'progress.json');

        return sendJson(response, 200, { ok: true, job: publicJob(record), progress });
    }

    return sendJson(response, 405, { ok: false, code: 'method_not_allowed', error: 'Unsupported operation for this endpoint.' });
}

const server = http.createServer((request, response) => {
    handle(request, response).catch((error) => {
        process.stderr.write(`[worker-server] ${error.stack ?? error.message}\n`);

        if (!response.headersSent) {
            sendJson(response, 500, { ok: false, code: 'server_error', error: 'The worker server encountered an error.' });
        } else {
            response.end();
        }
    });
});

server.requestTimeout = 120_000;
server.headersTimeout = 30_000;

await fs.mkdir(path.join(dataDir, 'jobs'), { recursive: true });

server.listen(port, host, () => {
    process.stdout.write(`[worker-server] listening on http://${host}:${port} (data: ${dataDir})\n`);
});

function shutdown(signal) {
    process.stdout.write(`[worker-server] received ${signal}, shutting down\n`);

    for (const record of jobs.values()) {
        if (record.pid) {
            try {
                process.kill(record.pid);
            } catch {
                // Already stopped.
            }
        }
    }

    server.close(() => process.exit(0));
    setTimeout(() => process.exit(0), 5000).unref();
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
