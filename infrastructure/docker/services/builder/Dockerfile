ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

# Default toys
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        make \
        nodejs \
        sudo \
        yarnpkg \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Config
COPY etc/. /etc/
RUN adduser app sudo \
    && ln -s /usr/bin/yarnpkg /usr/bin/yarn \
    && mkdir /var/log/php \
    && chmod 777 /var/log/php \
    && phpenmod app-default \
    && phpenmod app-builder

# Composer
COPY --from=composer:1.10.1 /usr/bin/composer /usr/bin/composer

WORKDIR /home/app/application
