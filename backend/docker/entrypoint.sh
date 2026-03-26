#!/bin/sh
set -e

# In production, Docker named volumes retain the ownership from when the volume
# was first created, not from the current image's RUN chown layer.  Fix that
# here, after the volume is already mounted, so PHP (www-data) can always write
# to storage and bootstrap/cache regardless of when the volume was initialised.
#
# Skipped in non-production environments to avoid touching host-owned
# bind-mounted files in development.
if [ "${APP_ENV:-local}" = "production" ]; then
    chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache
    chmod -R 775 \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache
fi

exec "$@"
