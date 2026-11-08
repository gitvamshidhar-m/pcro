import path from 'node:path';

const DEFAULT_VIEWPORT = { width: 1440, height: 1000 };
const DEFAULT_MOBILE_VIEWPORT = { width: 390, height: 844 };
const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; PredictiveCROBot/1.0; +https://example.com/bot)';

export class InvalidJobError extends Error {
    constructor(message, field) {
        super(message);
        this.name = 'InvalidJobError';
        this.code = 'invalid_job';
        this.field = field;
    }
}

function clampInteger(value, { fallback, min, max, field }) {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    const parsed = Number(value);

    if (!Number.isFinite(parsed)) {
        throw new InvalidJobError(`The "${field}" value must be a number.`, field);
    }

    return Math.min(Math.max(Math.trunc(parsed), min), max);
}

function normaliseViewport(value, fallback, field) {
    if (!value || typeof value !== 'object') {
        return { ...fallback };
    }

    const width = clampInteger(value.width, { fallback: fallback.width, min: 200, max: 3840, field: `${field}.width` });
    const height = clampInteger(value.height, { fallback: fallback.height, min: 200, max: 2160, field: `${field}.height` });

    return { width, height };
}

export function normaliseJob(raw, { baseDir = process.cwd() } = {}) {
    if (!raw || typeof raw !== 'object') {
        throw new InvalidJobError('The job payload must be a JSON object.');
    }

    const url = typeof raw.url === 'string' ? raw.url.trim() : '';

    if (url === '') {
        throw new InvalidJobError('The job payload requires a "url".', 'url');
    }

    let parsed;

    try {
        parsed = new URL(url);
    } catch {
        throw new InvalidJobError('The "url" value is not a valid absolute URL.', 'url');
    }

    if (!['http:', 'https:'].includes(parsed.protocol)) {
        throw new InvalidJobError('Only http and https URLs can be crawled.', 'url');
    }

    if (typeof raw.output_dir !== 'string' || raw.output_dir.trim() === '') {
        throw new InvalidJobError('The job payload requires an "output_dir".', 'output_dir');
    }

    const outputDir = path.isAbsolute(raw.output_dir)
        ? path.normalize(raw.output_dir)
        : path.resolve(baseDir, raw.output_dir);

    const jobId = typeof raw.job_id === 'string' && raw.job_id.trim() !== ''
        ? raw.job_id.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 80)
        : null;

    return {
        job_id: jobId,
        url: parsed.href,
        output_dir: outputDir,
        max_pages: clampInteger(raw.max_pages, { fallback: 30, min: 1, max: 60, field: 'max_pages' }),
        max_depth: clampInteger(raw.max_depth, { fallback: 3, min: 0, max: 5, field: 'max_depth' }),
        nav_timeout_ms: clampInteger(raw.nav_timeout_ms, { fallback: 45_000, min: 5_000, max: 120_000, field: 'nav_timeout_ms' }),
        page_timeout_ms: clampInteger(raw.page_timeout_ms, { fallback: 30_000, min: 5_000, max: 120_000, field: 'page_timeout_ms' }),
        delay_ms: clampInteger(raw.delay_ms, { fallback: 350, min: 0, max: 5_000, field: 'delay_ms' }),
        max_retries: clampInteger(raw.max_retries, { fallback: 1, min: 0, max: 3, field: 'max_retries' }),
        respect_robots: raw.respect_robots !== false,
        screenshot_quality: clampInteger(raw.screenshot_quality, { fallback: 65, min: 30, max: 95, field: 'screenshot_quality' }),
        user_agent: typeof raw.user_agent === 'string' && raw.user_agent.trim() !== ''
            ? raw.user_agent.trim().slice(0, 400)
            : DEFAULT_USER_AGENT,
        viewport: normaliseViewport(raw.viewport, DEFAULT_VIEWPORT, 'viewport'),
        mobile_viewport: normaliseViewport(raw.mobile_viewport, DEFAULT_MOBILE_VIEWPORT, 'mobile_viewport'),
        lighthouse: raw.lighthouse !== false,
        lighthouse_timeout_ms: clampInteger(raw.lighthouse_timeout_ms, { fallback: 120_000, min: 20_000, max: 300_000, field: 'lighthouse_timeout_ms' }),
        broken_link_checks: clampInteger(raw.broken_link_checks, { fallback: 12, min: 0, max: 30, field: 'broken_link_checks' }),
        discover_sitemaps: raw.discover_sitemaps !== false,
    };
}
