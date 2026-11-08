import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const AUDIT_KEYS = [
    'largest-contentful-paint',
    'cumulative-layout-shift',
    'total-blocking-time',
    'first-contentful-paint',
    'speed-index',
    'interactive',
    'server-response-time',
    'total-byte-weight',
    'unused-javascript',
    'unused-css',
    'render-blocking-resources',
    'modern-image-formats',
    'uses-optimized-images',
    'uses-responsive-images',
    'font-display',
    'third-party-summary',
];

export async function runLighthouse({
    url,
    chromePath,
    proxyPort,
    timeoutMs = 120_000,
    logger = () => {},
}) {
    const tempDir = await fs.mkdtemp(path.join(os.tmpdir(), 'pcro-lighthouse-'));
    const outputPath = path.join(tempDir, 'lighthouse.json');
    const cliPath = path.resolve('node_modules/lighthouse/cli/index.js');
    const chromeFlags = [
        '--headless=new',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--disable-quic',
        '--ignore-certificate-errors',
        `--proxy-server=http://127.0.0.1:${proxyPort}`,
        // Keep Chrome's implicit loopback bypass intact so Lighthouse can reach
        // its own DevTools endpoint. Only page traffic goes through the proxy.
    ].join(' ');

    const args = [
        cliPath,
        url,
        '--output=json',
        `--output-path=${outputPath}`,
        '--only-categories=performance,accessibility,best-practices,seo',
        '--form-factor=mobile',
        '--throttling-method=simulate',
        '--quiet',
        `--chrome-flags=${chromeFlags}`,
    ];

    logger(`lighthouse starting for ${url}`);

    const result = await new Promise((resolve) => {
        const child = spawn(process.execPath, args, {
            cwd: process.cwd(),
            env: {
                ...process.env,
                CHROME_PATH: chromePath,
                LIGHTHOUSE_CHROMIUM_PATH: chromePath,
            },
            windowsHide: true,
        });

        let stderr = '';
        let stdout = '';
        let settled = false;

        const finish = (payload) => {
            if (settled) {
                return;
            }

            settled = true;
            clearTimeout(timer);
            resolve(payload);
        };

        const timer = setTimeout(() => {
            child.kill('SIGKILL');
            finish({ ok: false, error: `Lighthouse timed out after ${Math.round(timeoutMs / 1000)}s.` });
        }, timeoutMs);

        child.stdout.on('data', (chunk) => {
            stdout += chunk.toString();
            if (stdout.length > 8000) {
                stdout = stdout.slice(-8000);
            }
        });

        child.stderr.on('data', (chunk) => {
            stderr += chunk.toString();
            if (stderr.length > 8000) {
                stderr = stderr.slice(-8000);
            }
        });

        child.on('error', (error) => finish({ ok: false, error: error.message }));
        child.on('close', (code) => finish({ ok: code === 0, code, stdout, stderr }));
    });

    if (!result.ok) {
        await fs.rm(tempDir, { recursive: true, force: true });

        return {
            available: false,
            error: (result.error || result.stderr || result.stdout || 'Lighthouse failed.').slice(0, 4000),
        };
    }

    try {
        const raw = JSON.parse(await fs.readFile(outputPath, 'utf8'));
        const categories = {};
        for (const [key, category] of Object.entries(raw.categories ?? {})) {
            categories[key] = category.score === null ? null : Math.round(category.score * 100);
        }

        const audits = {};
        for (const key of AUDIT_KEYS) {
            const audit = raw.audits?.[key];

            if (!audit) {
                continue;
            }

            audits[key] = {
                score: audit.score === null || audit.score === undefined ? null : audit.score,
                numeric_value: typeof audit.numericValue === 'number' ? Number(audit.numericValue.toFixed(3)) : null,
                display_value: audit.displayValue ?? null,
                title: audit.title ?? null,
            };
        }

        const diagnostics = (raw.audits?.['diagnostics']?.details?.items ?? []).slice(0, 10);

        return {
            available: true,
            fetched_at: new Date().toISOString(),
            lighthouse_version: raw.lighthouseVersion ?? null,
            requested_url: raw.requestedUrl ?? url,
            final_url: raw.finalDisplayedUrl ?? raw.finalUrl ?? url,
            fetch_time: raw.fetchTime ?? null,
            form_factor: raw.configSettings?.formFactor ?? 'mobile',
            categories,
            audits,
            diagnostics,
            warnings: (raw.runWarnings ?? []).slice(0, 10),
        };
    } catch (error) {
        return {
            available: false,
            error: `Could not parse Lighthouse output: ${error.message}`,
        };
    } finally {
        await fs.rm(tempDir, { recursive: true, force: true });
    }
}

export const lighthouseAuditKeys = AUDIT_KEYS;
