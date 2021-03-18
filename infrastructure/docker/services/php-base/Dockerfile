FROM debian:buster-slim

RUN apt-get update \
    && apt install -y --no-install-recommends \
        ca-certificates \
        gnupg \
    && echo "deb https://packages.sury.org/php buster main" > /etc/apt/sources.list.d/sury.list \
    && apt-key adv --fetch-keys https://packages.sury.org/php/apt.gpg

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        procps \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

ARG PHP_VERSION

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php${PHP_VERSION}-apcu \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-iconv \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-uuid \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Fake user to maps with the one on the host
ARG USER_ID
RUN addgroup --gid 1000 app && \
    adduser --system --uid $USER_ID --home /home/app --shell /bin/bash app

# Configuration
COPY php-configuration /etc/php/${PHP_VERSION}
