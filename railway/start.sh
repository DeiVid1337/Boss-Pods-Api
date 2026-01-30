#!/bin/sh
set -e
# Garante que o config seja lido dos arquivos (evita cache antigo com URL quebrada)
php artisan config:clear 2>/dev/null || true
php artisan migrate --force --no-interaction 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
# Railway espera o app escutando em $PORT; log para conferir nos Deploy Logs
echo "Starting Laravel on 0.0.0.0:${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
