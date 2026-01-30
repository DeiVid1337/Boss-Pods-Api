#!/bin/sh
set -e
# Garante que o config seja lido dos arquivos (evita cache antigo com URL quebrada)
php artisan config:clear 2>/dev/null || true
php artisan migrate --force --no-interaction 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
