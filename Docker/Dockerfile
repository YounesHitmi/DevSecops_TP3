# syntax=docker/dockerfile:1
# Image durcie (partie 5) : build multi-stage.
#  1. "vendor"  : installe les dépendances PHP avec Composer
#  2. "php-ext" : compile les extensions PHP (outils de compilation)
#  3. "tools"   : télécharge la CLI Infisical (version fixe + SHA-256)
#  4. image finale : seulement ce qui sert à l'exécution
# Les images de base sont épinglées par digest : Dependabot propose les mises à jour.

# ---------- 1. Dépendances PHP ----------
FROM composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# ext-gd n'est pas dans l'image composer, il est bien présent dans l'image finale
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress --ignore-platform-req=ext-gd
COPY src/ src/
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ---------- 2. Extensions PHP ----------
FROM php:8.2-apache@sha256:1bc4115a5528326425f1f59846fc7879d7d3843da9905b42931755ac4d4b29bc AS php-ext
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libsqlite3-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd pdo pdo_mysql zip pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

# ---------- 3. CLI Infisical ----------
FROM php:8.2-apache@sha256:1bc4115a5528326425f1f59846fc7879d7d3843da9905b42931755ac4d4b29bc AS tools
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
ARG INFISICAL_VERSION=0.45.4
ARG INFISICAL_SHA256=107389f4c9a0cc48023a834bb2a5bf1280c0a0e45ea7a98d13807d4def21b0a5
WORKDIR /tmp/infisical
RUN curl -fsSL -o cli.tar.gz \
        "https://github.com/Infisical/cli/releases/download/v${INFISICAL_VERSION}/cli_${INFISICAL_VERSION}_linux_amd64.tar.gz" \
 && echo "${INFISICAL_SHA256}  cli.tar.gz" | sha256sum -c - \
 && tar -xzf cli.tar.gz infisical \
 && install -m 0755 infisical /usr/local/bin/infisical

# ---------- 4. Image finale ----------
FROM php:8.2-apache@sha256:1bc4115a5528326425f1f59846fc7879d7d3843da9905b42931755ac4d4b29bc

ARG BUILD_DATE
ARG TAG
ARG GIT_COMMIT
ARG GIT_URL
ARG PROJECT_USERNAME="SI5"
ENV VERSION="${TAG}"

LABEL maintainer="${PROJECT_USERNAME}" \
      org.opencontainers.image.authors="${PROJECT_USERNAME}" \
      org.opencontainers.image.url="${GIT_URL}" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.vendor="${PROJECT_USERNAME}" \
      org.opencontainers.image.licenses="proprietary" \
      org.opencontainers.image.title="PHP Devops TP" \
      org.opencontainers.image.description="Example of a PHP application automatically encapsulated inside a docker container by the CI pipeline" \
      org.opencontainers.image.revision="${GIT_COMMIT}" \
      org.opencontainers.image.source="${GIT_URL}" \
      org.opencontainers.image.created="${BUILD_DATE}"

ENV DATABASE_DRIVER=sqlite
ENV DATABASE_DATABASE=/var/www/html/database/db.sqlite

# Mises à jour de sécurité Debian (ex. apache2 2.4.69 -> 2.4.71, CVE critique de mod_http2)
# + seulement les bibliothèques nécessaires à l'exécution des extensions
RUN apt-get update \
 && apt-get upgrade -y \
 && apt-get install -y --no-install-recommends libpng16-16t64 libjpeg62-turbo libfreetype6 libzip5 \
 && rm -rf /var/lib/apt/lists/*

COPY --from=php-ext /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-ext /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=tools /usr/local/bin/infisical /usr/local/bin/infisical

# Durcissement Apache et PHP : pas de version dans les en-têtes, config PHP de production
RUN a2enmod rewrite \
 && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/zz-security.conf \
 && a2enconf zz-security \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php = Off\ndisplay_errors = Off\n' > "$PHP_INI_DIR/conf.d/zz-security.ini"

WORKDIR /var/www/html

# Le code appartient à root : www-data (Apache) peut le lire mais pas le modifier.
# public/info.php est exclu par .dockerignore.
COPY --chown=root:www-data . /var/www/html
COPY --from=vendor --chown=root:www-data /app/vendor /var/www/html/vendor
RUN mv /var/www/html/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh \
 && chmod 0755 /usr/local/bin/docker-entrypoint.sh \
 && chown root:www-data /var/www/html \
 && chmod 0755 /var/www/html \
 && mkdir -p /var/www/html/database \
 && chown www-data:www-data /var/www/html/database

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --retries=3 CMD curl -fsS -o /dev/null http://localhost/ || exit 1

ENTRYPOINT ["docker-entrypoint.sh"]
# Définir un ENTRYPOINT efface le CMD de l'image de base : on le remet.
CMD ["apache2-foreground"]
