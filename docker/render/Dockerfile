# Dockerfile específico do Render — não usado pelo docker-compose.yml local (que continua
# PHP-FPM + Nginx em containers separados, ver docker/php/Dockerfile e docker/nginx/).
#
# Render exige 1 único processo/container escutando na porta que ele injeta via $PORT (não
# roda docker-compose) — este Dockerfile funde PHP-FPM + Nginx no mesmo container via
# supervisord, só pra esse alvo de deploy. Ver specs/deploy/fase-7-deploy-render.md.
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
        unzip \
        git \
        nginx \
        supervisor \
        gettext-base \
    && docker-php-ext-install pdo pdo_mysql mysqli intl mbstring zip gd \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-interaction --no-progress --prefer-dist \
    && chown -R www-data:www-data writable

COPY docker/render/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/render/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/render/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Porta padrão só pra documentação/build local — Render sobrescreve via $PORT em runtime
# (ver entrypoint.sh).
EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]
