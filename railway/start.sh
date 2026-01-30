#!/bin/sh
set -e
php artisan migrate --force --no-interaction 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
