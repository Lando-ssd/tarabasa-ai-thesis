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
# Loud, early warning in the server log if debug mode was left on: it shows internal details
# (paths, settings) on an error page. It never stops the site, it only says so.
case "$APP_DEBUG" in
    true|1|TRUE|True) echo "WARNING: APP_DEBUG is on. Set APP_DEBUG=false in the host variables." ;;
esac

php artisan storage:link --force || true
php artisan migrate --force

# Keep the Admin account in step with ADMIN_EMAIL / ADMIN_PASSWORD (it does nothing when
# ADMIN_PASSWORD is not set). `|| true`: a wrong value must never stop the site from starting.
php artisan admin:sync || true

# Ask the three teammate services, from this server, whether they answer, and write the result to the diary on the
# Admin dashboard ("Recent service problems"). In the background and never fatal: a sleeping free service can take a
# minute to answer and must not delay the site starting.
(php artisan services:check --quiet || true) &

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

# The background worker. Writing activities with the AI takes one to several minutes, far longer
# than a web request should be held open, so "Generate activities" saves the request and this
# worker writes it (GenerateActivitiesJob). The loop starts it again if it ever stops (it also
# restarts itself every 30 minutes, to keep its memory small). If it is not running for any
# reason, the Activities page that is watching the request writes it itself after 45 seconds.
# --timeout must be longer than the job's own limit (880), and --tries=1 because a request must
# never be written and charged twice.
(
    while true; do
        php artisan queue:work --sleep=2 --tries=1 --timeout=900 --max-time=1800 || true
        sleep 2
    done
) &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
