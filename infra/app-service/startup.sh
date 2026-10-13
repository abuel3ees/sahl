#!/bin/bash
# App Service startup command (siteConfig.appCommandLine). Runs on every container start,
# before the platform starts php-fpm. App Settings are already in the environment here.
set -e

cp /home/site/wwwroot/infra/app-service/nginx.conf /etc/nginx/sites-available/default
service nginx reload

cd /home/site/wwwroot
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

# Cache config, routes, views and events with the App Settings of this environment.
php artisan optimize
