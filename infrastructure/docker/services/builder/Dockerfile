ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

ARG NODEJS_VERSION=14.x
RUN echo "deb https://deb.nodesource.com/node_${NODEJS_VERSION} buster main" > /etc/apt/sources.list.d/nodejs.list \
    && apt-key adv --fetch-keys https://deb.nodesource.com/gpgkey/nodesource.gpg.key

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        make \
        nodejs \
        sudo \
        unzip \
    && apt-get clean \
    && npm install -g yarn \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Config
COPY etc/. /etc/
ARG PHP_VERSION
COPY php-configuration /etc/php/${PHP_VERSION}
RUN adduser app sudo \
    && mkdir /var/log/php \
    && chmod 777 /var/log/php \
    && phpenmod app-default \
    && phpenmod app-builder

# Composer
COPY --from=composer/composer:2.0.13 /usr/bin/composer /usr/bin/composer
RUN mkdir -p "/home/app/.composer/cache" \
    && chown app: /home/app/.composer -R

WORKDIR /home/app/application
