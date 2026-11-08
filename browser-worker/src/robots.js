import robotsParser from 'robots-parser';
import { resolveSafeTarget, parseTarget } from './url-safety.js';

export async function loadRobots(requestContext, entryUrl, userAgent, logger = () => {}) {
    try {
        const { url } = parseTarget(entryUrl);
        const robotsUrl = `${url.origin}/robots.txt`;
        const response = await requestContext.get(robotsUrl, {
            timeout: 15_000,
            maxRedirects: 5,
            headers: { 'user-agent': userAgent },
        });

        if (!response.ok()) {
            return {
                available: response.status() !== 404,
                status: response.status(),
                isAllowed: () => true,
                crawlDelay: () => 0,
                sitemaps: [],
                text: '',
            };
        }

        const text = await response.text();
        const parsed = robotsParser(robotsUrl, text);

        return {
            available: true,
            status: response.status(),
            isAllowed: (target, agent = userAgent) => parsed.isAllowed(target, agent) !== false,
            crawlDelay: (agent = userAgent) => Number(parsed.getCrawlDelay(agent) ?? 0),
            sitemaps: parsed.getSitemaps() ?? [],
            text,
        };
    } catch (error) {
        logger(`robots.txt unavailable: ${error.message}`);

        return {
            available: false,
            status: null,
            isAllowed: () => true,
            crawlDelay: () => 0,
            sitemaps: [],
            text: '',
            error: error.message,
        };
    }
}

export async function discoverSitemapUrls(requestContext, entryUrl, userAgent, limit = 60) {
    const { url } = parseTarget(entryUrl);
    const candidates = [`${url.origin}/sitemap.xml`, `${url.origin}/sitemap_index.xml`];
    const found = new Set();

    for (const sitemapUrl of candidates) {
        if (found.size >= limit) {
            break;
        }

        try {
            const response = await requestContext.get(sitemapUrl, {
                timeout: 15_000,
                maxRedirects: 5,
                headers: { 'user-agent': userAgent },
            });

            if (!response.ok()) {
                continue;
            }

            const text = await response.text();
            const matches = [...text.matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/gi)].slice(0, limit);

            for (const match of matches) {
                const loc = match[1].replaceAll('&amp;', '&');

                if (loc.startsWith('http://') || loc.startsWith('https://')) {
                    found.add(loc);
                }
            }
        } catch {
            // Sitemaps are optional discovery hints.
        }
    }

    return [...found].slice(0, limit);
}

export async function verifyTargetReachable(entryUrl, userAgent) {
    const { hostname } = parseTarget(entryUrl);
    return resolveSafeTarget(hostname, { fresh: true });
}
