import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';
import { getDomain } from 'tldts';
import { loadRobots, discoverSitemapUrls } from './robots.js';
import { createSafeProxy } from './safe-proxy.js';
import { extractMobileSignals, extractPage } from './extract.js';
import { runAxe } from './axe.js';
import { runLighthouse } from './lighthouse.js';
import { parseTarget, resolveSafeTarget } from './url-safety.js';

const SKIP_EXTENSIONS = new Set([
    'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'avif', 'ico', 'bmp',
    'pdf', 'zip', 'rar', '7z', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'mp4', 'mp3', 'avi', 'mov', 'wmv', 'webm', 'ogg', 'wav',
    'css', 'js', 'mjs', 'json', 'xml', 'txt', 'csv', 'rss',
    'woff', 'woff2', 'ttf', 'otf', 'eot', 'map',
]);

const TRACKING_PARAMS = new Set([
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'utm_id', 'gclid', 'fbclid', 'msclkid', 'yclid', 'mc_cid', 'mc_eid',
    'ref', 'referrer', 'source',
]);

const DESTRUCTIVE_PATH = /(?:logout|sign-?out|delete|remove|unsubscribe|opt-?out)/i;

export function normaliseCrawlUrl(input) {
    try {
        const url = new URL(input);

        if (!['http:', 'https:'].includes(url.protocol)) {
            return null;
        }

        url.hash = '';
        url.hostname = url.hostname.toLowerCase();

        for (const key of [...url.searchParams.keys()]) {
            if (TRACKING_PARAMS.has(key.toLowerCase())) {
                url.searchParams.delete(key);
            }
        }

        if (url.pathname !== '/' && url.pathname.endsWith('/')) {
            url.pathname = url.pathname.replace(/\/+$/, '');
        }

        return url.href;
    } catch {
        return null;
    }
}

export function isAssetUrl(url) {
    try {
        const pathname = new URL(url).pathname;
        const extension = pathname.split('.').pop()?.toLowerCase() ?? '';
        return SKIP_EXTENSIONS.has(extension) || DESTRUCTIVE_PATH.test(pathname);
    } catch {
        return true;
    }
}

export function isSameSite(candidate, base) {
    try {
        const candidateUrl = new URL(candidate);
        const baseUrl = new URL(base);

        if (candidateUrl.hostname === baseUrl.hostname) {
            return true;
        }

        const candidateDomain = getDomain(candidateUrl.hostname);
        const baseDomain = getDomain(baseUrl.hostname);

        return candidateDomain !== null && candidateDomain === baseDomain;
    } catch {
        return false;
    }
}

export function scoreLink(url, text = '', depth = 1) {
    let score = 20;
    let haystack = String(text).toLowerCase();

    try {
        const parsed = new URL(url);
        haystack += ` ${parsed.pathname}`.toLowerCase();
    } catch {
        return 0;
    }

    if (/contact|get-in-touch|enquir|inquir|quote|call-?back/.test(haystack)) {
        score = 100;
    } else if (/pricing|plans|packages|cost/.test(haystack)) {
        score = 95;
    } else if (/demo|book|consult|schedule/.test(haystack)) {
        score = 98;
    } else if (/product|service|solution|shop|store|collection/.test(haystack)) {
        score = 90;
    } else if (/about|team|company|who-we-are/.test(haystack)) {
        score = 74;
    } else if (/case-stud|testimonial|review|portfolio|work/.test(haystack)) {
        score = 72;
    } else if (/blog|insight|resource|article|news|guide/.test(haystack)) {
        score = 54;
    } else if (/legal|privacy|terms|cookie/.test(haystack)) {
        score = 8;
    }

    const segments = new URL(url).pathname.split('/').filter(Boolean).length;
    score -= depth * 2;
    score -= Math.max(0, segments - 3) * 8;

    return score;
}

function slugFor(url, index) {
    try {
        const parsed = new URL(url);
        const slug = `${parsed.pathname}${parsed.search}`
            .replace(/[^a-z0-9]+/gi, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60) || 'home';

        return `${String(index + 1).padStart(3, '0')}-${slug.toLowerCase()}`;
    } catch {
        return `${String(index + 1).padStart(3, '0')}-page`;
    }
}

async function autoScroll(page) {
    try {
        await page.evaluate(async () => {
            await new Promise((resolve) => {
                let total = 0;
                const limit = Math.min(document.documentElement.scrollHeight, 12_000);
                const step = () => {
                    window.scrollBy(0, 650);
                    total += 650;

                    if (total < limit) {
                        setTimeout(step, 70);
                    } else {
                        window.scrollTo(0, 0);
                        setTimeout(resolve, 180);
                    }
                };
                step();
            });
        });
    } catch {
        // Scrolling is best-effort; some pages block programmatic scrolling.
    }
}

function truncate(value, max = 3000) {
    return String(value ?? '').slice(0, max);
}

export class WebsiteCrawler {
    constructor(job, logger = () => {}) {
        this.job = job;
        this.logger = logger;
        this.maxPages = Math.min(Math.max(Number(job.max_pages) || 30, 1), 60);
        this.maxDepth = Math.min(Math.max(Number(job.max_depth) || 3, 0), 5);
        this.delayMs = Math.min(Math.max(Number(job.delay_ms) || 350, 0), 5000);
        this.pages = [];
        this.failures = [];
        this.blockedByRobots = 0;
        this.outputDir = job.output_dir;
        this.screenshotsDir = path.join(this.outputDir, 'screenshots');
        this.blockedRequests = [];
        this.lighthouse = null;
        this.robots = null;
        this.axePages = 0;
        this.visited = new Set();
    }

    async run() {
        await fs.mkdir(this.screenshotsDir, { recursive: true });

        const startedAt = new Date();
        const startedMs = Date.now();
        const entry = parseTarget(this.job.url);
        const baseUrl = normaliseCrawlUrl(entry.url.href) ?? entry.url.href;

        await resolveSafeTarget(entry.hostname, { fresh: true });

        const proxy = await createSafeProxy({ logger: this.logger });
        let browser;

        try {
            browser = await chromium.launch({
                headless: true,
                proxy: {
                    server: `http://127.0.0.1:${proxy.port}`,
                    bypass: '<-loopback>',
                },
                args: [
                    '--no-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-gpu',
                    '--disable-quic',
                    '--ignore-certificate-errors',
                    '--disable-background-networking',
                    '--disable-component-update',
                    '--disable-domain-reliability',
                    '--no-pings',
                ],
            });

            const context = await browser.newContext({
                userAgent: this.job.user_agent,
                viewport: this.job.viewport ?? { width: 1440, height: 1000 },
                locale: 'en-US',
                ignoreHTTPSErrors: true,
                colorScheme: 'light',
                bypassCSP: true,
                serviceWorkers: 'block',
            });

            context.setDefaultTimeout(Number(this.job.page_timeout_ms) || 30_000);
            context.setDefaultNavigationTimeout(Number(this.job.nav_timeout_ms) || 45_000);

            this.robots = this.job.respect_robots === false
                ? { available: false, status: null, isAllowed: () => true, crawlDelay: () => 0, sitemaps: [], text: '' }
                : await loadRobots(context.request, baseUrl, this.job.user_agent, this.logger);

            const queue = [{ url: baseUrl, depth: 0, priority: 1000, label: 'Homepage' }];
            const queued = new Set([baseUrl]);
            const axeTargets = new Set();
            let homePage = null;
            let homeResponse = null;

            if (this.job.discover_sitemaps !== false) {
                const sitemapUrls = await discoverSitemapUrls(context.request, baseUrl, this.job.user_agent, 60);

                for (const sitemapUrl of sitemapUrls) {
                    const normalised = normaliseCrawlUrl(sitemapUrl);

                    if (
                        normalised
                        && !queued.has(normalised)
                        && !isAssetUrl(normalised)
                        && isSameSite(normalised, baseUrl)
                        && this.robots.isAllowed(normalised, this.job.user_agent)
                    ) {
                        queue.push({ url: normalised, depth: 1, priority: 65, label: 'Sitemap' });
                        queued.add(normalised);
                    }
                }
            }

            const page = await context.newPage();
            let pageErrors = [];
            page.on('pageerror', (error) => {
                if (pageErrors.length < 20) {
                    pageErrors.push(truncate(error.message, 500));
                }
            });

            while (queue.length > 0 && this.pages.length < this.maxPages) {
                queue.sort((a, b) => b.priority - a.priority || a.depth - b.depth);
                const next = queue.shift();

                if (!next || this.visited.has(next.url)) {
                    continue;
                }

                if (next.depth > this.maxDepth) {
                    continue;
                }

                this.visited.add(next.url);
                const index = this.pages.length;

                if (!this.robots.isAllowed(next.url, this.job.user_agent)) {
                    this.blockedByRobots += 1;
                    this.failures.push({ url: next.url, error: 'Disallowed by robots.txt', code: 'robots_disallowed' });
                    continue;
                }

                this.logger(`crawl ${index + 1}/${this.maxPages}: ${next.url}`);
                pageErrors = [];

                let response = null;
                let navigationError = null;
                const attempts = Math.max(1, Math.min(Number(this.job.max_retries) || 1, 3) + 1);

                for (let attempt = 1; attempt <= attempts; attempt++) {
                    try {
                        response = await page.goto(next.url, {
                            waitUntil: 'domcontentloaded',
                            timeout: Number(this.job.nav_timeout_ms) || 45_000,
                        });
                        navigationError = null;
                        break;
                    } catch (error) {
                        navigationError = error;
                        this.logger(`navigation attempt ${attempt} failed for ${next.url}: ${error.message}`);

                        if (attempt < attempts) {
                            await new Promise((resolve) => setTimeout(resolve, 500 * attempt));
                        }
                    }
                }

                if (navigationError || response === null) {
                    this.failures.push({
                        url: next.url,
                        error: truncate(navigationError?.message ?? 'Navigation returned no response.', 500),
                        code: 'navigation_failed',
                        depth: next.depth,
                    });
                    await this.writeProgress('crawling', next.url);
                    continue;
                }

                const status = response.status();

                if (status >= 400) {
                    this.failures.push({
                        url: next.url,
                        status_code: status,
                        error: `HTTP ${status}`,
                        code: status === 429 ? 'rate_limited' : 'http_error',
                        depth: next.depth,
                    });
                    await this.writeProgress('crawling', next.url);
                    continue;
                }

                try {
                    await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
                    await autoScroll(page);
                    const extracted = await extractPage(page);
                    const finalUrl = normaliseCrawlUrl(extracted.final_url) ?? next.url;
                    const screenshot = await this.captureScreenshot(page, next.url, index, next.depth);
                    const jsErrors = [...pageErrors];

                    const pageData = {
                        ...extracted,
                        url: next.url,
                        final_url: finalUrl,
                        requested_url: next.url,
                        depth: next.depth,
                        priority: next.priority,
                        status_code: status,
                        response_headers: this.sanitiseHeaders(response.headers()),
                        screenshot,
                        js_errors: jsErrors,
                        axe: null,
                    };

                    if (index === 0) {
                        homePage = pageData;
                        homeResponse = response;
                    }

                    if (index < 5 || next.priority >= 90) {
                        if (this.axePages < 5) {
                            pageData.axe = await runAxe(page);
                            this.axePages += 1;
                            axeTargets.add(next.url);
                        }
                    }

                    this.pages.push(pageData);

                    for (const link of extracted.links) {
                        if (!link.href || !isSameSite(link.href, baseUrl) || isAssetUrl(link.href)) {
                            continue;
                        }

                        const normalised = normaliseCrawlUrl(link.href);

                        if (!normalised || queued.has(normalised) || this.visited.has(normalised)) {
                            continue;
                        }

                        if (!this.robots.isAllowed(normalised, this.job.user_agent)) {
                            continue;
                        }

                        const depth = next.depth + 1;

                        if (depth > this.maxDepth) {
                            continue;
                        }

                        queue.push({
                            url: normalised,
                            depth,
                            priority: scoreLink(normalised, link.text, depth),
                            label: link.text,
                        });
                        queued.add(normalised);
                    }

                    for (const sitemapUrl of this.robots.sitemaps ?? []) {
                        const normalised = normaliseCrawlUrl(sitemapUrl);
                        if (normalised && isSameSite(normalised, baseUrl) && !queued.has(normalised) && !isAssetUrl(normalised)) {
                            queue.push({ url: normalised, depth: 2, priority: 45, label: 'Sitemap' });
                            queued.add(normalised);
                        }
                    }
                } catch (error) {
                    this.failures.push({
                        url: next.url,
                        status_code: status,
                        error: truncate(error.message, 600),
                        code: 'extraction_failed',
                        depth: next.depth,
                    });
                }

                await this.writeProgress('crawling', next.url);
                const crawlDelay = Number(this.robots.crawlDelay(this.job.user_agent) ?? 0);
                const wait = Math.min(Math.max(this.delayMs, crawlDelay * 1000), 5000);

                if (wait > 0) {
                    await new Promise((resolve) => setTimeout(resolve, wait));
                }
            }

            if (homePage === null) {
                const firstFailure = this.failures[0];
                const error = new Error(firstFailure
                    ? `The homepage could not be analysed: ${firstFailure.error}`
                    : 'The homepage could not be analysed.');
                error.code = 'homepage_failed';
                error.details = this.failures.slice(0, 5);
                throw error;
            }

            await this.writeProgress('mobile', baseUrl);
            let mobile = null;

            try {
                mobile = await this.captureMobile(browser, homePage.url);
            } catch (error) {
                this.logger(`mobile capture failed: ${error.message}`);
            }

            if (this.job.lighthouse !== false) {
                await this.writeProgress('lighthouse', homePage.url);
                this.lighthouse = await runLighthouse({
                    url: homePage.url,
                    chromePath: chromium.executablePath(),
                    proxyPort: proxy.port,
                    timeoutMs: Number(this.job.lighthouse_timeout_ms) || 120_000,
                    logger: this.logger,
                });
            }

            await this.writeProgress('links', baseUrl);
            const brokenLinks = await this.checkBrokenLinks(context, homePage);

            this.blockedRequests = proxy.getBlocked();
            const completedAt = new Date();
            const siteName = homePage.og?.site_name
                ?? homePage.structured_name
                ?? homePage.title
                ?? new URL(baseUrl).hostname;

            const result = {
                ok: true,
                schema_version: 1,
                generated_at: completedAt.toISOString(),
                crawl: {
                    entry_url: baseUrl,
                    final_url: homePage.final_url,
                    domain: new URL(baseUrl).hostname,
                    site_name: truncate(siteName, 300),
                    title: homePage.title,
                    meta_description: homePage.meta_description,
                    lang: homePage.lang,
                    favicon: homePage.favicon,
                    logo: homePage.og?.image ?? null,
                    has_ssl: new URL(homePage.final_url).protocol === 'https:',
                    security_headers: this.securityHeaders(homePage.response_headers ?? {}),
                    robots: {
                        available: this.robots.available,
                        status: this.robots.status,
                        disallowed_skipped: this.blockedByRobots,
                    },
                    mobile,
                    lighthouse: this.lighthouse,
                    blocked_requests: this.blockedRequests,
                    broken_links: brokenLinks,
                },
                stats: {
                    pages_discovered: this.visited.size + queue.length,
                    pages_crawled: this.pages.length,
                    pages_failed: this.failures.length,
                    robots_blocked: this.blockedByRobots,
                    axe_pages: axeTargets.size,
                    duration_ms: Date.now() - startedMs,
                    lighthouse_available: Boolean(this.lighthouse?.available),
                },
                pages: this.pages,
                failures: this.failures.slice(0, 100),
                started_at: startedAt.toISOString(),
                completed_at: completedAt.toISOString(),
            };

            await fs.writeFile(path.join(this.outputDir, 'result.json'), JSON.stringify(result), 'utf8');
            await this.writeProgress('completed', baseUrl, { progress: 100 });

            return result;
        } catch (error) {
            await fs.writeFile(path.join(this.outputDir, 'error.json'), JSON.stringify({
                ok: false,
                code: error.code ?? 'crawl_failed',
                error: error.message,
                details: error.details ?? [],
                blocked_requests: proxy.getBlocked(),
            }), 'utf8');

            throw error;
        } finally {
            await browser?.close().catch(() => {});
            await proxy.close().catch(() => {});
        }
    }

    async captureScreenshot(page, url, index, depth) {
        const relative = `screenshots/${slugFor(url, index)}.jpg`;
        const absolute = path.join(this.outputDir, relative);
        const fullPageCandidate = index === 0 || /contact|pricing|service|product|about|demo/i.test(new URL(url).pathname);
        let fullPage = null;

        try {
            await page.screenshot({
                path: absolute,
                type: 'jpeg',
                quality: Number(this.job.screenshot_quality) || 65,
                fullPage: false,
            });

            if (fullPageCandidate) {
                const height = await page.evaluate(() => document.documentElement.scrollHeight);

                if (height <= 10_000) {
                    const fullRelative = `screenshots/${slugFor(url, index)}-full.jpg`;
                    await page.screenshot({
                        path: path.join(this.outputDir, fullRelative),
                        type: 'jpeg',
                        quality: Number(this.job.screenshot_quality) || 65,
                        fullPage: true,
                    });
                    fullPage = fullRelative;
                }
            }
        } catch (error) {
            this.logger(`screenshot failed for ${url}: ${error.message}`);
            return null;
        }

        return { viewport: relative, full: fullPage };
    }

    async captureMobile(browser, url) {
        const context = await browser.newContext({
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            viewport: this.job.mobile_viewport ?? { width: 390, height: 844 },
            isMobile: true,
            hasTouch: true,
            deviceScaleFactor: 2,
            ignoreHTTPSErrors: true,
            bypassCSP: true,
            serviceWorkers: 'block',
        });

        try {
            const page = await context.newPage();
            const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45_000 });
            await page.waitForLoadState('networkidle', { timeout: 6000 }).catch(() => {});
            await autoScroll(page);
            const signals = await extractMobileSignals(page);
            const relative = 'screenshots/mobile-home.jpg';
            await page.screenshot({
                path: path.join(this.outputDir, relative),
                type: 'jpeg',
                quality: Number(this.job.screenshot_quality) || 65,
                fullPage: false,
            });

            return {
                ...signals,
                status_code: response?.status() ?? null,
                screenshot: relative,
            };
        } finally {
            await context.close();
        }
    }

    async checkBrokenLinks(context, homePage) {
        const limit = Math.min(Math.max(Number(this.job.broken_link_checks) || 12, 0), 30);
        const seen = new Set();
        const candidates = [];

        for (const page of [homePage, ...this.pages.slice(0, 10)]) {
            for (const link of page.links ?? []) {
                if (!link.href || !isSameSite(link.href, homePage.url) || isAssetUrl(link.href)) {
                    continue;
                }

                const normalised = normaliseCrawlUrl(link.href);

                if (!normalised || seen.has(normalised) || normalised === normaliseCrawlUrl(homePage.url)) {
                    continue;
                }

                seen.add(normalised);
                candidates.push(normalised);

                if (candidates.length >= limit) {
                    break;
                }
            }

            if (candidates.length >= limit) {
                break;
            }
        }

        const broken = [];

        for (const url of candidates) {
            try {
                let response = await context.request.head(url, {
                    timeout: 12_000,
                    maxRedirects: 0,
                    failOnStatusCode: false,
                    headers: { 'user-agent': this.job.user_agent },
                });

                if ([405, 501].includes(response.status())) {
                    response = await context.request.get(url, {
                        timeout: 12_000,
                        maxRedirects: 0,
                        failOnStatusCode: false,
                        headers: { 'user-agent': this.job.user_agent },
                    });
                }

                if (response.status() >= 400) {
                    broken.push({ url, status_code: response.status(), source: 'crawl' });
                }
            } catch (error) {
                broken.push({ url, status_code: null, error: truncate(error.message, 300), source: 'crawl' });
            }
        }

        return broken.slice(0, 30);
    }

    securityHeaders(headers) {
        const normalised = Object.fromEntries(Object.entries(headers).map(([key, value]) => [key.toLowerCase(), value]));

        return {
            strict_transport_security: normalised['strict-transport-security'] ?? null,
            content_security_policy: truncate(normalised['content-security-policy'] ?? null, 500),
            x_frame_options: normalised['x-frame-options'] ?? null,
            x_content_type_options: normalised['x-content-type-options'] ?? null,
            referrer_policy: normalised['referrer-policy'] ?? null,
            permissions_policy: truncate(normalised['permissions-policy'] ?? null, 300),
            server: normalised.server ?? null,
            content_type: normalised['content-type'] ?? null,
        };
    }

    sanitiseHeaders(headers) {
        const allowed = ['content-type', 'server', 'strict-transport-security', 'content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'cache-control'];
        const result = {};

        for (const [key, value] of Object.entries(headers)) {
            if (allowed.includes(key.toLowerCase())) {
                result[key.toLowerCase()] = truncate(value, 500);
            }
        }

        return result;
    }

    async writeProgress(stage, currentUrl, extra = {}) {
        const progress = extra.progress ?? Math.min(95, 8 + Math.round((this.pages.length / Math.max(this.maxPages, 1)) * 82));

        await fs.writeFile(path.join(this.outputDir, 'progress.json'), JSON.stringify({
            stage,
            current_url: truncate(currentUrl, 2000),
            pages_crawled: this.pages.length,
            pages_discovered: this.visited.size,
            pages_failed: this.failures.length,
            progress,
            updated_at: new Date().toISOString(),
        }), 'utf8').catch(() => {});
    }
}
