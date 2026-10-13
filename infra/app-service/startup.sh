#!/bin/bash
# App Service startup command (siteConfig.appCommandLine). Runs on every container start,
# before the platform starts php-fpm. App Settings are already in the environment here.
set -e

cp /home/site/wwwroot/infra/app-service/nginx.conf /etc/nginx/sites-available/default
service nginx reload

# OpenTelemetry PHP extension (tracing). The image doesn't include it, and the container's disk is wiped on every
# restart, so it's compiled once (~1 min) and kept in /home, which survives. The PHP version is in the file name:
# when Azure updates PHP, the next start builds a matching one. Must load before artisan runs below.
OTEL_EXT_VERSION=1.4.2
OTEL_EXT="/home/site/ext/opentelemetry-${OTEL_EXT_VERSION}-php$(php -r 'echo PHP_VERSION;').so"

if [ ! -f "$OTEL_EXT" ]; then
    echo "Building the opentelemetry extension ${OTEL_EXT_VERSION} (first start on this PHP version)..."
    mkdir -p /home/site/ext
    # Tracing must never take the site down: if the build fails, start without it.
    if pecl install "opentelemetry-${OTEL_EXT_VERSION}" > /tmp/pecl-opentelemetry.log 2>&1; then
        cp "$(php-config --extension-dir)/opentelemetry.so" "$OTEL_EXT"
    else
        echo "WARNING: opentelemetry extension build failed, starting without tracing. Log: /tmp/pecl-opentelemetry.log"
        tail -n 20 /tmp/pecl-opentelemetry.log
    fi
fi

if [ -f "$OTEL_EXT" ]; then
    echo "extension=${OTEL_EXT}" > "${PHP_INI_DIR:-/usr/local/etc/php}/conf.d/zz-opentelemetry.ini"
fi

cd /home/site/wwwroot
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

# Cache config, routes, views and events with the App Settings of this environment.
php artisan optimize

# Bring the database schema up to date with the code just deployed. --isolated takes a lock in the cache store,
# so two instances starting together never migrate at the same time. A failing migration stops the start (set -e).
php artisan migrate --force --isolated
