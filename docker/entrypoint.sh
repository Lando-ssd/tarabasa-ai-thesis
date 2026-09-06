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

# php artisan serve is single-threaded by default — it can only handle one
# request at a time. That's fatal in production: while it's blocked on a
# slow real external API call (Gemini for activity generation, Vosk for
# reading scoring — both routinely take several seconds), it can't also
# answer Railway's own health check on /up. Enough missed health checks
# and Railway restarts the container mid-request, silently killing the
# in-flight request and wiping the file-based session — confirmed as the
# real cause of a live production bug (see CLAUDE.md). PHP's built-in
# server supports a real multi-worker mode for exactly this; baked in
# here rather than left as a Railway dashboard variable, which already
# proved easy to lose by accident once.
export PHP_CLI_SERVER_WORKERS=4

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
