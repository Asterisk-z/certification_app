# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — build the Vue/Tailwind assets
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources/ resources/
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 2 — install PHP dependencies
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 3 — runtime: PHP-FPM + Node + Chromium (for Browsershot PDFs)
# ---------------------------------------------------------------------------
FROM php:8.2-fpm-bookworm AS app

WORKDIR /var/www/html

# System packages: Chromium + the libraries and fonts it needs for clean PDF
# rendering, plus Node.js 22 (NodeSource — puppeteer needs Node 20+) for
# Browsershot's puppeteer bridge.
RUN apt-get update && apt-get install -y --no-install-recommends \
        curl ca-certificates gnupg \
        chromium \
        fonts-liberation fonts-dejavu-core fontconfig \
        supervisor libcap2-bin \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libicu-dev \
        zip unzip git default-mysql-client \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql gd zip bcmath exif pcntl intl opcache \
    && setcap "cap_net_bind_service=+ep" /usr/local/bin/php \
    && apt-get -y autoremove && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Browsershot resolves the `puppeteer` npm package from the project root.
# .npmrc skips the bundled-Chromium download — we point at system Chromium.
COPY package.json package-lock.json .npmrc ./
RUN npm ci --omit=dev && npm cache clean --force

# Application code, vendor and built assets
COPY . .
COPY --from=vendor /app/vendor/ vendor/
COPY --from=assets /app/public/build/ public/build/

RUN cp -n .env.example .env \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    # www-data's home — Chromium/puppeteer need a writable ~/.cache & ~/.config
    && chown www-data:www-data /var/www

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV CHROME_PATH=/usr/bin/chromium

# 9000 = php-fpm (nginx pairing), 80 = supervisor mode (artisan serve).
EXPOSE 9000 80

ENTRYPOINT ["entrypoint.sh"]
# Default is php-fpm for the nginx pairing; production overrides this with
# supervisord (web + queue + scheduler in one container).
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4 — nginx with the public assets baked in
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
