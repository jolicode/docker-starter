# hadolint global ignore=DL3008

FROM debian:12.8-slim AS php-base

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
        gnupg \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php bookworm main" > /etc/apt/sources.list.d/sury.list \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash-completion \
        procps \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

ARG PHP_VERSION

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        "php${PHP_VERSION}-apcu" \
        "php${PHP_VERSION}-bcmath" \
        "php${PHP_VERSION}-cli" \
        "php${PHP_VERSION}-common" \
        "php${PHP_VERSION}-curl" \
        "php${PHP_VERSION}-iconv" \
        "php${PHP_VERSION}-intl" \
        "php${PHP_VERSION}-mbstring" \
        "php${PHP_VERSION}-pgsql" \
        "php${PHP_VERSION}-uuid" \
        "php${PHP_VERSION}-xml" \
        "php${PHP_VERSION}-zip" \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Configuration
COPY base/php-configuration /etc/php/${PHP_VERSION}

ENV PHP_VERSION=${PHP_VERSION}
ENV HOME=/home/app
ENV COMPOSER_MEMORY_LIMIT=-1

WORKDIR /var/www

FROM php-base AS frontend

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        "php${PHP_VERSION}-fpm" \
        runit \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && rm -r "/etc/php/${PHP_VERSION}/fpm/pool.d/"

RUN useradd -s /bin/false nginx

COPY frontend/php-configuration /etc/php/${PHP_VERSION}
COPY frontend/etc/nginx/. /etc/nginx/
RUN rm -rf /etc/service/
COPY frontend/etc/service/. /etc/service/
RUN chmod 777 /etc/service/*/supervise/

RUN phpenmod app-default \
    && phpenmod app-fpm

EXPOSE 80

CMD ["runsvdir", "-P", "/etc/service"]

FROM php-base AS worker

FROM php-base AS builder

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

ARG NODEJS_VERSION=20.x
RUN curl -s https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODEJS_VERSION} nodistro main" > /etc/apt/sources.list.d/nodesource.list

# Default toys
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        "git" \
        "make" \
        "nodejs" \
        "php${PHP_VERSION}-dev" \
        "sudo" \
        "unzip" \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && corepack enable \
    && yarn set version stable

# Install a fake sudo command
# This is commented out by default because it exposes a security risk if you use this image in production, but it may be useful for development
# Use it at your own risk
# COPY base/sudo.sh /usr/local/bin/sudo
# RUN curl -L https://github.com/tianon/gosu/releases/download/1.16/gosu-amd64 -o /usr/local/bin/gosu && \
#    chmod u+s /usr/local/bin/gosu && \
#    chmod +x /usr/local/bin/gosu && \
#    chmod +x /usr/local/bin/sudo

# Config
COPY builder/php-configuration /etc/php/${PHP_VERSION}
RUN phpenmod app-default \
    && phpenmod app-builder

# Composer
COPY --from=composer/composer:2.8.9 /usr/bin/composer /usr/bin/composer

# Pie
RUN curl -L --output /usr/local/bin/pie https://github.com/php/pie/releases/download/1.0.0/pie.phar \
    && chmod +x /usr/local/bin/pie

# Autocompletion
ADD https://raw.githubusercontent.com/symfony/symfony/refs/heads/7.3/src/Symfony/Component/Console/Resources/completion.bash /tmp/completion.bash

# Composer symfony/console version is too old, and doest not support "API version feature", so we remove it
# Hey, while we are at it, let's add some more completion
RUN sed /tmp/completion.bash \
        -e "s/{{ COMMAND_NAME }}/composer/g" \
        -e 's/"-a{{ VERSION }}"//g' \
        -e "s/{{ VERSION }}/1/g"  \
        > /etc/bash_completion.d/composer \
    && sed /tmp/completion.bash \
        -e "s/{{ COMMAND_NAME }}/console/g" \
        -e "s/{{ VERSION }}/1/g"  \
        > /etc/bash_completion.d/console

# Third party tools
ENV PATH="$PATH:/var/www/tools/bin"

# Good default customization
RUN cat >> /etc/bash.bashrc <<EOF
. /etc/bash_completion

PS1='\[\e[01;33m\]\u \[\e[00;32m\]\w\[\e[0m\] '
EOF

WORKDIR /var/www/application
