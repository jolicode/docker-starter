FROM myapp_basephp

RUN apk --no-cache add curl python py2-pip mariadb-client nodejs make g++ php7-phar git yarn

RUN chown -R myapp /usr/local /usr/bin /usr/lib

USER myapp

# Install pycodestyle
RUN pip install pycodestyle

# Install composer
RUN curl https://getcomposer.org/composer.phar > /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer

RUN composer global require "hirak/prestissimo:^0.3" --prefer-dist --no-progress --no-suggest --optimize-autoloader --classmap-authoritative \
&& composer clear-cache

# Add composer global binaries to $PATH
ENV PATH $PATH:/home/myapp/.composer/vendor/bin/
ENV TERM xterm

WORKDIR /home/myapp/app

CMD ["echo"]
