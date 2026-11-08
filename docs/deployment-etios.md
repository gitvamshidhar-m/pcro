# Deployment on ETIOS (and similar shared hosts)

This guide covers deploying **Predictive CRO Intelligence** on ETIOS. It separates
steps that are verified in this repository from steps that must be run on the
ETIOS server by the operator, because ETIOS cannot be inspected from here.

## Architecture options

### Option A — Single host (process worker)

Use this when the ETIOS host can run PHP, MySQL, Node.js and Chromium.

- Laravel serves the web application.
- The browser worker runs as a child process (`CRO_WORKER_MODE=process`).
- Playwright/Chromium run on the same machine.

### Option B — Split host (HTTP worker) — use when Node/Chromium are unavailable

Use this when ETIOS cannot run Node.js or Chromium (common on shared hosting).

- Laravel runs on ETIOS (`CRO_WORKER_MODE=http`).
- A separate Linux VM/VPS runs `browser-worker/server.js` with `PCRO_WORKER_TOKEN`.
- Laravel talks to it over HTTPS with a bearer token.
- Screenshots are only available when both hosts share an output volume
  (`CRO_WORKER_SHARED_ROOT`). Otherwise the crawl still completes, but
  screenshot previews are unavailable.

## Verified locally

The following have been verified in this repository:

- `php artisan migrate --force` succeeds on MySQL 8.4.
- `php artisan test --compact` passes (101 tests).
- `vendor/bin/pint --format agent` produces no errors.
- The browser worker passes `node --test` (12 tests).
- A real crawl of `https://example.com` captured screenshots, mobile signals,
  axe results and Lighthouse scores (performance 88, accessibility 100,
  best-practices 100, SEO 80).
- SSRF protection blocks private IPs, internal hostnames and non-standard ports.
- A queued end-to-end crawl through Laravel completed successfully.

## ETIOS preflight (run on the server)

Save and run `scripts/etios-preflight.sh` on ETIOS. It checks:

- PHP version (8.3+ required, 8.4 tested)
- Required PHP extensions: pdo_mysql, mbstring, xml, zip, gd, curl, openssl, fileinfo
- MySQL/MariaDB connectivity
- Node.js and npm (only for Option A)
- Chromium/Playwright availability (only for Option A)
- Writable directories: `storage/`, `bootstrap/cache/`, `public/`

## Installation steps (operator-run on ETIOS)

1. Upload the repository to the application root.
2. Copy `.env.example` to `.env` and set:
   - `APP_KEY` (`php artisan key:generate`)
   - `APP_URL`
   - database credentials
   - `CRO_WORKER_MODE` (`process` or `http`)
   - for HTTP mode: `CRO_WORKER_URL`, `CRO_WORKER_TOKEN`
   - optional: `CRO_AI_API_KEY`
3. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Install and build frontend assets:
   ```bash
   npm ci && npm run build
   ```
5. Run migrations and seed audit rules:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=AuditRuleSeeder --force
   ```
6. Link storage:
   ```bash
   php artisan storage:link
   ```
7. Set permissions:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
8. Configure the web server document root to `public/`.
9. Configure a process manager to run:
   - `php artisan queue:work --sleep=3 --tries=1 --timeout=1260 --queue=crawls,analysis`
   - (Option A only) nothing else; the worker is spawned by Laravel.
   - (Option B) the remote worker service, plus a tunnel/reverse proxy exposing it to Laravel.
10. (Option A only) Install Chromium:
    ```bash
    cd browser-worker && npm ci && npx playwright install chromium
    ```

## Separate browser-worker service (Option B)

On the worker host:

```bash
cd browser-worker
npm ci
npx playwright install chromium
PCRO_WORKER_TOKEN=your-secret PCRO_WORKER_HOST=0.0.0.0 node server.js
```

Health check:

```bash
curl -H "Authorization: Bearer your-secret" http://worker-host:8787/health
```

## Queue and timeouts

- Crawl jobs run on the `crawls` queue.
- Analysis jobs run on the `analysis` queue.
- A Lighthouse-enabled crawl can take around two minutes.
- Keep `CRO_WORKER_MAX_RUNTIME` and the queue worker `--timeout` above 1200 seconds.

## Security notes

- The application blocks private IPs, internal hostnames and non-standard ports.
- The browser worker enforces the same rules through its filtering proxy.
- Never expose the worker without a bearer token.
- Scrub `.env` secrets before committing or sharing.
