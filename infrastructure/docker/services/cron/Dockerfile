ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

COPY crontab /etc/crontabs/app

CMD ["crond", "-l", "0", "-f"]
