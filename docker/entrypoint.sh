#!/bin/bash
set -e

# Clear and warm up Symfony cache
php bin/console cache:clear --env=${APP_ENV:-prod} || true
php bin/console cache:warmup --env=${APP_ENV:-prod} || true

# Start supervisord for background workers
if [ "$APP_ENV" = "prod" ]; then
    exec /usr/bin/supervisord -c /etc/supervisord.conf
else
    # In test/dev mode, just start php-fpm
    exec php-fpm
fi
