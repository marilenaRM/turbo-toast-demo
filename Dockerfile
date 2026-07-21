# syntax=docker/dockerfile:1

# Dev image for the turbo-toast demo. The application source and the sibling
# bundle are bind-mounted by compose.yaml; Composer dependencies are installed
# at container start, because the bundle is a local Composer *path* repository
# (`../TurboToastBundle`), not a Packagist package that could be baked in.
FROM dunglas/frankenphp:1-php8.3

# ctype + iconv are bundled in the base image; add intl/opcache (Symfony) and
# zip (Composer needs it to extract dist archives).
RUN install-php-extensions intl opcache zip

# Composer, straight from its official image.
COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# Serve plain HTTP on :80 (no auto-TLS for a local demo).
ENV SERVER_NAME=:80 \
    APP_ENV=dev \
    APP_DEBUG=1

WORKDIR /app
