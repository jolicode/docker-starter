ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

RUN apk add --no-cache \
    nginx \
    php7-fpm \
    runit

COPY etc/. /etc/

EXPOSE 80

CMD ["runsvdir", "-P", "/etc/service"]
