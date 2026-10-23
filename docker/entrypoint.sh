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

# php artisan serve is single-threaded by default: it can only handle one request at a time. That is fatal in
# production. While it is blocked on a slow real call (Gemini for activity generation, Vosk for reading scoring, both
# routinely take several seconds, and a sleeping service can take a minute), it cannot answer anything else, including
# the host's own health check, and the host then stops sending visitors to it (a 502 for everyone). Render's events on
# 2026-10-22 and 2026-10-23 were all "HTTP health check failed (timed out after 5 seconds)".
#
# PHP's built-in server has a real multi-worker mode, PHP_CLI_SERVER_WORKERS, baked in here rather than left as a host
# dashboard variable, which already proved easy to lose by accident once. BUT Laravel's `artisan serve` ONLY honours it
# together with --no-reload (the flag that stops it restarting the server when .env changes, which a container does not
# have). Without the flag it prints "Unable to respect the PHP_CLI_SERVER_WORKERS environment variable without the
# --no-reload flag. Only creating a single server." and runs ONE worker. That is exactly what ran in production, silently,
# until the deploy log was read on 2026-10-23: the setting below looked fine and did nothing. Keep --no-reload on the
# serve line, and check that the host's deploy log does NOT contain that warning (tests/Feature/ServerStartupTest.php
# pins the flag).
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
        php artisan queue:work --queue=default --sleep=2 --tries=1 --timeout=900 --max-time=1800 || true
        sleep 2
    done
) &

# The scoring workers. A child's recording used to be scored INSIDE the web request, which held one of the four web
# workers for 5 to 45 seconds while the speech service worked, and a class pressing "done" together left none free for
# the host's health check (a 502 for everybody). Now the web request only saves the recording and a worker here scores
# it (ScoreRecordingJob, App\Services\BackgroundScoring) while the child's page asks "is it done?" every couple of
# seconds. They have their own queue so a recording never waits behind the minutes-long activity writing above.
#
# SCORING_WORKERS (default 2) is how many run. Each is a PHP process, about as large as a web worker, and the free host
# has 512 MB: if the host's memory graph ever shows it near the limit, set SCORING_WORKERS=1 in the host's variables
# (no code change). The speech service itself may only score one recording at a time (not measured), which would then be the real limit.
# --sleep=2: an idle worker looks for work every 2 seconds. --timeout is longer than the job's own wait (config/scoring.php).
i=1
while [ "$i" -le "${SCORING_WORKERS:-2}" ]; do
    (
        while true; do
            php artisan queue:work --queue=scoring --sleep=2 --tries=1 --timeout=240 --max-time=1800 || true
            sleep 2
        done
    ) &
    i=$((i + 1))
done

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
