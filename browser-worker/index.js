#!/usr/bin/env node
import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { InvalidJobError, normaliseJob } from './src/job.js';
import { WebsiteCrawler } from './src/crawler.js';

function parseArgs(argv) {
    const options = { input: null, output: null, quiet: false };

    for (let index = 0; index < argv.length; index += 1) {
        const argument = argv[index];

        if (argument === '--input' || argument === '-i') {
            options.input = argv[index + 1] ?? null;
            index += 1;
        } else if (argument.startsWith('--input=')) {
            options.input = argument.slice('--input='.length);
        } else if (argument === '--output' || argument === '-o') {
            options.output = argv[index + 1] ?? null;
            index += 1;
        } else if (argument.startsWith('--output=')) {
            options.output = argument.slice('--output='.length);
        } else if (argument === '--quiet' || argument === '-q') {
            options.quiet = true;
        } else if (argument === '--help' || argument === '-h') {
            process.stdout.write([
                'Predictive CRO browser worker',
                '',
                'Usage: node index.js [--input job.json] [--output result.json] [--quiet]',
                '',
                'When --input is omitted the job JSON is read from stdin.',
                'The worker writes result.json and error.json into output_dir.',
                '',
            ].join('\n'));
            process.exit(0);
        }
    }

    return options;
}

async function readStdin() {
    const chunks = [];

    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }

    return Buffer.concat(chunks).toString('utf8');
}

async function main() {
    const options = parseArgs(process.argv.slice(2));
    const raw = options.input
        ? await fs.readFile(path.resolve(options.input), 'utf8')
        : await readStdin();

    let payload;

    try {
        payload = JSON.parse(raw);
    } catch (error) {
        throw new InvalidJobError(`The job payload is not valid JSON: ${error.message}`);
    }

    const job = normaliseJob(payload, { baseDir: process.cwd() });
    await fs.mkdir(job.output_dir, { recursive: true });

    const log = options.quiet
        ? () => {}
        : (message) => process.stderr.write(`[worker] ${message}\n`);

    const crawler = new WebsiteCrawler(job, log);
    const result = await crawler.run();

    const outputPath = options.output
        ? path.resolve(options.output)
        : path.join(job.output_dir, 'result.json');

    if (outputPath !== path.join(job.output_dir, 'result.json')) {
        await fs.writeFile(outputPath, JSON.stringify(result), 'utf8');
    }

    if (!options.quiet) {
        process.stdout.write(`${JSON.stringify({
            ok: true,
            result_path: outputPath,
            pages_crawled: result.stats.pages_crawled,
            pages_failed: result.stats.pages_failed,
            duration_ms: result.stats.duration_ms,
            lighthouse_available: result.stats.lighthouse_available,
        })}\n`);
    }

    return 0;
}

main()
    .then((code) => process.exit(code))
    .catch(async (error) => {
        const isInvalidJob = error instanceof InvalidJobError;
        const payload = {
            ok: false,
            code: error.code ?? 'crawl_failed',
            error: isInvalidJob ? error.message : 'The crawl failed.',
            message: error.message,
            field: error.field ?? null,
            details: error.details ?? [],
        };

        process.stderr.write(`${JSON.stringify(payload)}\n`);
        process.exit(isInvalidJob ? 2 : 1);
    });
