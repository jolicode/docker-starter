ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

ARG PHP_VERSION

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx \
        php${PHP_VERSION}-fpm \
        runit \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN useradd -s /bin/false nginx

COPY etc/. /etc/
COPY php-configuration /etc/php/${PHP_VERSION}

RUN phpenmod app-default \
    && phpenmod app-fpm

EXPOSE 80

CMD ["runsvdir", "-P", "/etc/service"]
