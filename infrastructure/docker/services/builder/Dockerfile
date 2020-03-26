ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

RUN apk add --no-cache \
    curl \
    g++ \
    git \
    make \
    nodejs \
    npm \
    php7-phar \
    py2-pip \
    python \
    yarn

RUN pip install pycodestyle

COPY --from=composer:1.10.1 /usr/bin/composer /usr/bin/composer

RUN mkdir -p ~/.composer

ENV PATH $PATH:/home/app/.composer/vendor/bin/
ENV TERM xterm

WORKDIR /home/app/application

CMD ["echo"]
