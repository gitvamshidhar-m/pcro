#!/usr/bin/env bash
set -euo pipefail

echo "== Predictive CRO Intelligence: ETIOS preflight =="

fail=0

check() {
    if command -v "$1" >/dev/null 2>&1; then
        echo "[OK] $1: $(command -v "$1")"
    else
        echo "[MISSING] $1"
        fail=1
    fi
}

check php
check mysql
check composer
check node
check npm

echo
echo "PHP version:"
php -r 'echo PHP_VERSION, PHP_EOL;'
php -r 'echo version_compare(PHP_VERSION, "8.3.0", ">=") ? "PHP 8.3+ OK" : "PHP 8.3+ REQUIRED"; echo PHP_EOL;'

echo
echo "PHP extensions:"
for ext in pdo_mysql mbstring xml zip gd curl openssl fileinfo; do
    if php -m | grep -qi "^${ext}$"; then
        echo "[OK] ${ext}"
    else
        echo "[MISSING] ${ext}"
        fail=1
    fi
done

echo
echo "MySQL connectivity (optional, requires DB_* env):"
if [ -n "${DB_HOST:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
    if mysql -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD:-}" -e "SELECT 1" >/dev/null 2>&1; then
        echo "[OK] MySQL reachable"
    else
        echo "[WARN] MySQL not reachable with supplied credentials"
    fi
else
    echo "[SKIP] DB_HOST/DB_USERNAME not set"
fi

echo
echo "Node/Chromium (Option A only):"
if command -v node >/dev/null 2>&1; then
    node --version
    if [ -d "browser-worker/node_modules/playwright" ]; then
        echo "[OK] Playwright installed"
    else
        echo "[INFO] Playwright not installed (run npm ci in browser-worker)"
    fi
else
    echo "[INFO] Node.js not available; use Option B (HTTP worker)"
fi

echo
echo "Writable paths:"
for dir in storage bootstrap/cache public; do
    if [ -w "$dir" ]; then
        echo "[OK] $dir writable"
    else
        echo "[WARN] $dir not writable"
        fail=1
    fi
done

if [ "$fail" -ne 0 ]; then
    echo
    echo "Preflight completed with warnings/errors."
    exit 1
fi

echo
echo "Preflight completed successfully."
