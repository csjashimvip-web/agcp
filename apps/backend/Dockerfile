FROM php:8.4-fpm-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends bash curl git unzip default-mysql-client libicu-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring opcache pcntl pdo_mysql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/99-agcp.ini
COPY docker/opcache.ini /usr/local/etc/php/conf.d/10-opcache.ini
WORKDIR /var/www/html

# Production dependencies are resolved during image build, where network access is available.
FROM base AS vendor-production
COPY composer.* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# Development dependencies are also baked into the image. They are later copied into
# the named vendor volume, so runtime containers do not need internet access.
FROM base AS vendor-development
COPY composer.* ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    && sha256sum composer.json | cut -d' ' -f1 > vendor/.agcp-dependency-hash

FROM base AS development
COPY --from=vendor-development /var/www/html/vendor /opt/agcp/vendor
COPY docker/entrypoint.sh /usr/local/bin/agcp-entrypoint
RUN chmod +x /usr/local/bin/agcp-entrypoint
ENTRYPOINT ["agcp-entrypoint"]
CMD ["php-fpm", "-F"]

FROM base AS runtime
ENV APP_ENV=production APP_DEBUG=false
COPY --chown=www-data:www-data . .
COPY --from=vendor-production --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY docker/entrypoint.sh /usr/local/bin/agcp-entrypoint
RUN chmod +x /usr/local/bin/agcp-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction
USER www-data
ENTRYPOINT ["agcp-entrypoint"]
CMD ["php-fpm", "-F"]
