#!/usr/bin/env bash
#
# Production deploy for Balloonventory on the cPanel VPS.
#
# The default `composer` and `php` on this host resolve to PHP 8.2, which
# doesn't satisfy the lock file (Symfony 8 packages require PHP 8.4+). Composer
# must be invoked through the 8.4 CLI explicitly — this script handles that
# plus the standard Laravel deploy sequence.
#
# Usage from the server (cd to app root first):
#   bash bin/deploy.sh
#
# Or from your laptop in one shot:
#   ssh myvps "cd /home/balloonventory/balloonventory-app && bash bin/deploy.sh"

set -euo pipefail

PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER=/opt/cpanel/composer/bin/composer

export NVM_DIR="$HOME/.nvm"
# shellcheck source=/dev/null
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Sourcing nvm.sh does not put node/npm on PATH in a non-interactive SSH
# session — select a version explicitly, then fail loudly if it's still missing.
nvm use default >/dev/null 2>&1 || nvm use node >/dev/null 2>&1 || true

if ! command -v npm >/dev/null 2>&1; then
    echo "ERROR: npm not found on PATH after loading nvm." >&2
    echo "Set a default node version on the server: nvm alias default <version>" >&2
    exit 1
fi

echo "== git pull =="
git fetch
git checkout main
git pull

echo "== composer install =="
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader

echo "== npm build =="
npm ci --prefer-offline
npm run build

echo "== migrate =="
"$PHP" artisan migrate --force

echo "== rebuild caches =="
"$PHP" artisan optimize

echo "== done =="
