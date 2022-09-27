ARG PROJECT_NAME

FROM ${PROJECT_NAME}-php-base

WORKDIR /home/app/application

COPY entrypoint.sh /var/lib

ENTRYPOINT ["/var/lib/entrypoint.sh"]
