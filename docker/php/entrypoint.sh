#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=.\+' .env; then
    php artisan key:generate --force
fi

php artisan config:clear

until php artisan migrate --force --no-interaction 2>/dev/null; do
    echo "Waiting for the database to become available..."
    sleep 2
done

exec "$@"
