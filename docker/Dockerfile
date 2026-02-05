FROM dunglas/frankenphp:1-php8.4-alpine AS builder

RUN install-php-extensions pdo_pgsql pgsql intl zip opcache ldap gd mbstring xml json apcu
WORKDIR /app
COPY . /app
RUN mkdir -p /app/var

FROM dunglas/frankenphp:1-php8.4-alpine AS dev
WORKDIR /app
COPY --from=builder /app /app
RUN install-php-extensions xdebug pdo_pgsql pgsql intl zip opcache ldap gd mbstring xml json apcu
ENV XDEBUG_MODE=develop,debug
COPY docker/php/dev.ini /usr/local/etc/php/conf.d/dev.ini
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

FROM dunglas/frankenphp:1-php8.4-alpine AS stable
WORKDIR /app
COPY --from=builder /app /app
RUN install-php-extensions pdo_pgsql pgsql intl zip opcache ldap gd mbstring xml json apcu
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/prod.ini
COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
