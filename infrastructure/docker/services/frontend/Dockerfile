ARG PROJECT_NAME

FROM ${PROJECT_NAME}_basephp

RUN apk add --no-cache \
    nginx \
    php7-fpm \
    runit

COPY etc/. /etc/

CMD ["runsvdir", "-P", "/etc/service"]
