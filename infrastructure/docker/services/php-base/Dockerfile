FROM debian:buster-slim

RUN apt-get update \
    && apt install -y --no-install-recommends \
        ca-certificates \
        gnupg \
        curl \
        unzip \
        build-essential \
        autoconf \
        libcurl4-openssl-dev \
    && echo "deb https://packages.sury.org/php buster main" > /etc/apt/sources.list.d/sury.list \
    && apt-key adv --keyserver keyserver.ubuntu.com --recv-keys B188E2B695BD4743

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        procps \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

ARG PHP_VERSION=7.4
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php${PHP_VERSION}-apcu \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-iconv \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Compile APM Agent
# Installing shared extensions: /usr/lib/php/20190902/
RUN curl https://github.com/elastic/apm-agent-php/archive/master.zip -L -o agent.zip
RUN unzip agent.zip

WORKDIR /apm-agent-php-master/src/ext
RUN phpize \
    && ./configure --enable-elastic_apm \
    && make clean \
    && make \
    && make install

# Fake user to maps with the one on the host
ARG USER_ID
RUN addgroup --gid 1000 app && \
    adduser --system --uid $USER_ID --home /home/app --shell /bin/bash app

# Configuration
COPY etc/. /etc/
