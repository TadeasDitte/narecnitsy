#!/bin/sh
set -e

# Fails fast with a clear message instead of the app crash-looping with
# cryptic "headers already sent" errors on every request (Laravel's own
# exception handler struggles to render a clean error page for a missing
# key). Every service - app, queue, hyperliquid-stream, migrate - shares
# this image and this entrypoint, so this check protects all of them.
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set - refusing to start." >&2
    echo "Generate one with: docker run --rm <image> php artisan key:generate --show" >&2
    echo "then put it in your .env as APP_KEY=base64:... and restart." >&2
    exit 1
fi

exec "$@"
