#!/bin/sh
set -e

cd /var/www/html

# The storage volume starts empty — recreate the framework's directory
# skeleton so sessions, caches, logs and generated PDFs have a home.
mkdir -p \
    storage/app/public \
    storage/app/certificates \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Generate one with:"
    echo "  docker compose run --rm app php artisan key:generate --show"
    echo "and put it in your .env file."
    exit 1
fi

# SQLite lives on the shared storage volume so web, queue and scheduler all
# see the same database file.
if [ "$DB_CONNECTION" = "sqlite" ] && [ -n "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
    # The directory must be writable too — WAL mode creates -wal/-shm files.
    chown -R www-data:www-data "$(dirname "$DB_DATABASE")"
fi

# Wait for the database before migrating (first boot of MySQL can be slow).
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    echo "Waiting for database at $DB_HOST..."
    tries=0
    until php -r 'new PDO(sprintf("mysql:host=%s;port=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' 2>/dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -gt 60 ]; then
            echo "Database never became ready." >&2
            exit 1
        fi
        sleep 2
    done
fi

# Only the main app container runs migrations/links, so the queue worker and
# scheduler don't race it. They set SKIP_BOOTSTRAP=1.
if [ "$SKIP_BOOTSTRAP" != "1" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    php artisan storage:link --force || true
    php artisan config:cache
    php artisan view:cache
    # NOTE: route:cache is skipped — the SPA catch-all is a closure route.
fi

exec "$@"
