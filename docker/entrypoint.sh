#!/bin/sh
set -e

# The host platform (Railway, Render, or anything else) only injects real
# secrets as environment variables, not a .env file — .env itself is
# gitignored and never shipped in the image, so Laravel reads config
# straight from the container's env at boot. No config:cache here since
# that would freeze today's env into the image layer if it were ever
# built with values present. $PORT is injected the same way by both
# Railway and Render — the app must bind to whatever port it's given,
# not a hardcoded one.
php artisan storage:link --force || true
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
