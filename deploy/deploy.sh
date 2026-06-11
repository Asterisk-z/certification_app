#!/bin/sh
# Server-side deploy for setup.certification.hseboard.com.
# Run from anywhere: /var/www/setup.certification.hseboard.com/deploy/deploy.sh
set -e

APP_DIR="${APP_DIR:-/var/www/setup.certification.hseboard.com}"
cd "$APP_DIR"

if [ ! -f .env ]; then
    echo "ERROR: $APP_DIR/.env is missing."
    echo "  cp .env.production.example .env   # then fill in the values"
    exit 1
fi

echo "==> Pulling latest code"
git fetch origin main
git reset --hard origin/main

echo "==> Building and starting containers"
docker compose -f docker-compose.prod.yml up -d --build

echo "==> Cleaning up dangling images"
docker image prune -f >/dev/null

echo "==> Status"
docker compose -f docker-compose.prod.yml ps --format 'table {{.Service}}\t{{.Status}}'

echo "Deploy complete."
