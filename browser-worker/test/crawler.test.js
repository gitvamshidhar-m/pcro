import assert from 'node:assert/strict';
import test from 'node:test';
import { InvalidJobError, normaliseJob } from '../src/job.js';
import { isAssetUrl, isSameSite, normaliseCrawlUrl, scoreLink } from '../src/crawler.js';

const baseJob = {
    url: 'https://Example.com/',
    output_dir: 'storage/crawls/01ABC',
};

test('normalises a minimal job with safe defaults', () => {
    const job = normaliseJob(baseJob, { baseDir: 'E:/app' });

    assert.equal(job.url, 'https://example.com/');
    assert.equal(job.max_pages, 30);
    assert.equal(job.max_depth, 3);
    assert.equal(job.respect_robots, true);
    assert.equal(job.lighthouse, true);
    assert.equal(job.output_dir, 'E:/app/storage/crawls/01ABC'.replaceAll('/', '\\'));
});

test('clamps numeric ranges and rejects bad values', () => {
    const job = normaliseJob({ ...baseJob, max_pages: 500, max_depth: -2, max_retries: 9 }, { baseDir: 'E:/app' });

    assert.equal(job.max_pages, 60);
    assert.equal(job.max_depth, 0);
    assert.equal(job.max_retries, 3);

    assert.throws(() => normaliseJob({ ...baseJob, max_pages: 'many' }, { baseDir: 'E:/app' }), InvalidJobError);
});

test('requires a URL and an output directory', () => {
    assert.throws(() => normaliseJob({ output_dir: '/tmp/x' }), InvalidJobError);
    assert.throws(() => normaliseJob({ url: 'https://example.com' }), InvalidJobError);
    assert.throws(() => normaliseJob({ ...baseJob, url: 'ftp://example.com' }), InvalidJobError);
});

test('normalises crawl URLs and strips tracking parameters', () => {
    assert.equal(
        normaliseCrawlUrl('https://Example.com/pricing/?utm_source=ad&plan=pro#top'),
        'https://example.com/pricing?plan=pro',
    );
    assert.equal(normaliseCrawlUrl('mailto:hi@example.com'), null);
});

test('detects asset and destructive URLs', () => {
    for (const url of [
        'https://example.com/image.png',
        'https://example.com/app.js',
        'https://example.com/file.pdf',
        'https://example.com/logout',
    ]) {
        assert.equal(isAssetUrl(url), true, `${url} should be skipped`);
    }

    assert.equal(isAssetUrl('https://example.com/pricing'), false);
    assert.equal(isAssetUrl('https://example.com/blog/how-to-cro'), false);
});

test('compares same-site URLs including subdomains', () => {
    assert.equal(isSameSite('https://example.com/about', 'https://www.example.com/'), true);
    assert.equal(isSameSite('https://blog.example.com/post', 'https://example.com/'), true);
    assert.equal(isSameSite('https://other.example/post', 'https://example.com/'), false);
    assert.equal(isSameSite('https://example.org/', 'https://example.com/'), false);
});

test('scores conversion-critical links above blog and legal pages', () => {
    const contact = scoreLink('https://example.com/contact', 'Contact us', 1);
    const pricing = scoreLink('https://example.com/pricing', 'Pricing', 1);
    const blog = scoreLink('https://example.com/blog/post', 'Article', 1);
    const legal = scoreLink('https://example.com/privacy', 'Privacy policy', 1);

    assert.ok(contact >= 90, `contact scored ${contact}`);
    assert.ok(pricing >= 90, `pricing scored ${pricing}`);
    assert.ok(contact > blog);
    assert.ok(blog > legal);
});
