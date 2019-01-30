ARG PROJECT_NAME

FROM ${PROJECT_NAME}_basephp

RUN apk add --no-cache \
    curl \
    g++ \
    git \
    make \
    mariadb-client \
    nodejs \
    npm \
    php7-phar \
    py2-pip \
    python \
    yarn

RUN pip install pycodestyle

RUN curl https://getcomposer.org/composer.phar -o /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

RUN mkdir -p ~/.composer

ENV PATH $PATH:/home/app/.composer/vendor/bin/
ENV TERM xterm

WORKDIR /home/app/app

CMD ["echo"]
