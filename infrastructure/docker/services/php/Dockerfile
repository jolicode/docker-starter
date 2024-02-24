# hadolint global ignore=DL3008

FROM debian:12.5-slim as php-base

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

# Fake user to maps with the one on the host
ARG USER_ID
COPY entrypoint /
RUN addgroup --gid $USER_ID app && \
    adduser --system --uid $USER_ID --home /home/app --shell /bin/bash app && \
    curl -Ls https://github.com/tianon/gosu/releases/download/1.17/gosu-amd64 | \
        install /dev/stdin /usr/local/bin/gosu && \
    sed "s/{{ application_user }}/app/g" -i /entrypoint

# Configuration
COPY base/php-configuration /etc/php/${PHP_VERSION}

WORKDIR /var/www
ENTRYPOINT [ "/entrypoint" ]

FROM php-base as frontend

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
COPY frontend/etc/service/. /etc/service/

RUN phpenmod app-default \
    && phpenmod app-fpm

EXPOSE 80

CMD ["runsvdir", "-P", "/etc/service"]

FROM php-base as worker

FROM php-base as builder

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

ARG NODEJS_VERSION=20.x
RUN curl -s https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODEJS_VERSION} nodistro main" > /etc/apt/sources.list.d/nodesource.list

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        make \
        nodejs \
        sudo \
        unzip \
    && apt-get clean \
    && npm install -g yarn@1.22 \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Config
COPY builder/etc/. /etc/
COPY builder/php-configuration /etc/php/${PHP_VERSION}
RUN adduser app sudo \
    && mkdir /var/log/php \
    && chmod 777 /var/log/php \
    && phpenmod app-default \
    && phpenmod app-builder

# Composer
COPY --from=composer/composer:2.7.1 /usr/bin/composer /usr/bin/composer
RUN mkdir -p "/home/app/.composer/cache" \
    && chown app: /home/app/.composer -R

# Third party tools
ENV PATH="$PATH:/var/www/tools/bin"

WORKDIR /var/www/application
