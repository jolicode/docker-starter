<p align="center">
    <img width="500" height="180" src="https://jolicode.com/media/original/docker-starter-logo.png" alt="Docker starter kit logo" />
</p>

<p align="center">
    <i>Collection of Dockerfile and docker-compose configurations wrapped in an easy-to-use command line, oriented for PHP projects.</i>
</p>

## What is Docker Starter Kit

This repository provides a Docker infrastructure for your PHP projects with
built-in support for HTTPS, custom domain, databases, workers... and is used as
a foundation for our projects at [JoliCode](https://jolicode.com/).

> [!WARNING]
> You are reading the README of version 4 that uses [castor](https://github.com/jolicode/castor).

* If you are using [Invoke](https://www.pyinvoke.org/), you can read the [dedicated README](https://github.com/jolicode/docker-starter/tree/v3.11.0);
* If you are using [Fabric](https://www.fabfile.org/), you can read the [dedicated README](https://github.com/jolicode/docker-starter/tree/v2.0.0);

## Project configuration

Before executing any command, you need to configure a few parameters in the
`castor.php` file, in the `create_default_variables()` function:

* `project_name` (**required**): This will be used to prefix all docker objects
(network, images, containers);

* `root_domain` (optional, default: `project_name + '.test'`): This is the root
domain where the application will be available;

* `extra_domains` (optional): This contains extra domains where the application
will be available;

* `php_version` (optional, default: `8.3`): This is PHP version.

For example:

```php
function create_default_variables(): Context
{
    $projectName = 'app';
    $tld = 'test';

    return [
        'project_name' => $projectName,
        'root_domain' => "{$projectName}.{$tld}",
        'extra_domains' => [
            "www.{$projectName}.{$tld}",
            "admin.{$projectName}.{$tld}",
            "api.{$projectName}.{$tld}",
        ],
        'php_version' => 8.3,
    ];
)
```

Will give you `https://app.test`,  `https://www.app.test`,
`https://api.app.test` and `https://admin.app.test` pointing at your
`application/` directory.

> [!NOTE]
> Some castor tasks have been added for DX purposes. Checkout and adapt
> the tasks `install`, `migrate` and `cache_clear` to your project.

## Usage documentation

We provide a [README.dist.md](./README.dist.md) to bootstrap your project
documentation, with everything you need to know to start and interact with the
infrastructure.

If you want to install a Symfony project, you can run (before `castor init`):

```
castor symfony [--web-app]
```

To replace this README with the dist, and remove all unnecessary files, you can
run:

```bash
castor init
```

> [!NOTE]
> This command can be run only once

Also, in order to improve your usage of castor scripts, you can install console
autocompletion script.

If you are using bash:

```bash
castor completion | sudo tee /etc/bash_completion.d/castor
```

If you are using something else, please refer to your shell documentation. You
may need to use `castor completion > /to/somewhere`.

Castor supports completion for `bash`, `zsh` & `fish` shells.

## Cookbooks

### How to install third party tools with Composer

<details>

<summary>Read the cookbook</summary>

If you want to install some third party tools with Composer, it is recommended to install them in their dedicated directory.
PHPStan and PHP-CS-Fixer are already installed in the `tools` directory.

We suggest to:

1. create a composer.json which requires only this tool in `tools/<tool name>/composer.json`;

1. create an executable symbolic link to the tool from the root directory of the project: `ln -s ../<tool name>/vendor/bin/<tool bin> tools/bin/<tool bin>`;

> [!NOTE]
> Relative symlinks works here, because the first part of the command is relative to the second part, not to the current directory.

Since `tools/bin` path is appended to the `$PATH`, tools will be available globally in the builder container.

</details>

### How to change the layout of the project

<details>

<summary>Read the cookbook</summary>

If you want to rename the `application` directory, or even move its content to
the root directory, you have to edit each reference to it. Theses references
represent each application entry point, whether it be over HTTP or CLI.
Usually, there is three places where you need to do it:

* In Nginx configuration file:
  `infrastructure/docker/services/php/frontend/etc/nginx/nginx.conf`. You need
  to update  `http.server.root` option to the new path. For example:
  ```diff
  - root /var/www/application/public;
  + root /var/www/public;
  ```
* In all workers configuration file:
  `infrastructure/docker/docker-compose.worker.yml`:
  ```diff
  - command: php -d memory_limit=1G /var/www/application/bin/console messenger:consume async --memory-limit=128M
  + command: php -d memory_limit=1G /var/www/bin/console messenger:consume async --memory-limit=128M
  ```
* In the builder, to land in the right directory directly:
  `infrastructure/docker/services/php/Dockerfile`:
  ```diff
  - WORKDIR /var/www/application
  + WORKDIR /var/www
  ```

</details>

### How to use with Webpack Encore

<details>

<summary>Read the cookbook</summary>

> [!NOTE]
> this cookbook documents the integration of webpack 5+. For older version
> of webpack, use previous version of the docker starter.

If you want to use Webpack Encore in a Symfony project,

1. Follow [instructions on symfony.com](https://symfony.com/doc/current/frontend/encore/installation.html#installing-encore-in-symfony-applications) to install webpack encore.

    You will need to follow [these instructions](https://symfony.com/doc/current/frontend/encore/simple-example.html) too.

2. Create a new service for encore:

    Add the following content to the `docker-compose.yml` file:

    ```yaml
    services:
        encore:
            build:
                context: services/php
                target: builder
            volumes:
                - "../..:/var/www:cached"
            command: "yarn run dev-server --hot --host 0.0.0.0 --allowed-hosts encore.${PROJECT_ROOT_DOMAIN} --allowed-hosts ${PROJECT_ROOT_DOMAIN} --client-web-socket-url-hostname encore.${PROJECT_ROOT_DOMAIN} --client-web-socket-url-port 443 --client-web-socket-url-protocol wss"
            labels:
                - "traefik.enable=true"
                - "project-name=${PROJECT_NAME}"
                - "traefik.http.routers.${PROJECT_NAME}-encore.rule=Host(`encore.${PROJECT_ROOT_DOMAIN}`)"
                - "traefik.http.routers.${PROJECT_NAME}-encore.tls=true"
                - "traefik.http.services.encore.loadbalancer.server.port=8080"
            profiles:
                - default
    ```

3. Update the webpack configuration to specify the asset location in **dev**:

    ```diff
    diff --git a/application/webpack.config.js b/application/webpack.config.js
    index 056b04a..766c590 100644
    --- a/application/webpack.config.js
    +++ b/application/webpack.config.js
    @@ -6,13 +6,22 @@ if (!Encore.isRuntimeEnvironmentConfigured()) {
        Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
    }

    +
    +if (Encore.isProduction()) {
    +    Encore
    +        // public path used by the web server to access the output path
    +        .setPublicPath('/build')
    +        // only needed for CDN's or sub-directory deploy
    +        //.setManifestKeyPrefix('build/')
    +} else {
    +    Encore
    +        .setPublicPath('https://encore.app.test/build')
    +        .setManifestKeyPrefix('build/')
    +}
    +
    Encore
        // directory where compiled assets will be stored
        .setOutputPath('public/build/')
    -    // public path used by the web server to access the output path
    -    .setPublicPath('/build')
    -    // only needed for CDN's or sub-directory deploy
    -    //.setManifestKeyPrefix('build/')

        /*
        * ENTRY CONFIG
    ```

If the assets are not reachable, you may accept self-signed certificate. To do so, open a new tab
at https://encore.app.test and click on accept.

</details>

### How to use with AssetMapper

<details>

<summary>Read the cookbook</summary>

1. Follow [instructions on symfony.com](https://symfony.com/doc/current/frontend/asset_mapper.html#installation) to install AssetMapper.

1. Remove this block in the
`infrastructure/docker/services/php/frontend/etc/nginx/nginx.conf` file:

    ```
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg)$ {
        access_log off;
        add_header Cache-Control "no-cache";
    }
    ```

1. Remove these lines in the `infrastructure/docker/services/php/Dockerfile` file:

    ```diff
    SHELL ["/bin/bash", "-o", "pipefail", "-c"]

    - ARG NODEJS_VERSION=18.x
    - RUN curl -s https://deb.nodesource.com/gpgkey/nodesource.gpg.key | gpg --dearmor > /usr/share/keyrings/nodesource.gpg \
    -     && echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODEJS_VERSION} bullseye main" > /etc/apt/sources.list.d/nodejs.list

    # Default toys
    RUN apt-get update \
        && apt-get install -y --no-install-recommends \
            git \
            make \
    -       nodejs \
            sudo \
            unzip \
        && apt-get clean \
    -   && npm install -g yarn@1.22 \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
    ```
</details>

### How to add Elasticsearch and Kibana

<details>

<summary>Read the cookbook</summary>

In order to use Elasticsearch and Kibana, you should add the following content
to the `docker-compose.yml` file:

```yaml
volumes:
    elasticsearch-data: {}

services:
    elasticsearch:
        image: elasticsearch:7.8.0
        volumes:
            - elasticsearch-data:/usr/share/elasticsearch/data
        environment:
            - "discovery.type=single-node"
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.rule=Host(`elasticsearch.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.tls=true"
        profiles:
            - default

    kibana:
        image: kibana:7.8.0
        depends_on:
            - elasticsearch
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-kibana.rule=Host(`kibana.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-kibana.tls=true"
        profiles:
            - default
```

Then, you will be able to browse:

* `https://kibana.<root_domain>`
* `https://elasticsearch.<root_domain>`

In your application, you can use the following configuration:

* scheme: `http`;
* host: `elasticsearch`;
* port: `9200`.

</details>

### How to use with Sylius

<details>

<summary>Read the cookbook</summary>

Add the php extension `gd` to `infrastructure/docker/services/php/Dockerfile`

```
php${PHP_VERSION}-gd \
```

If you want to create a new Sylius project, you need to enter a builder (`inv
builder`) and run the following commands

1. Remove the `application` folder:

    ```bash
    cd ..
    rm -rf application/*
    ```

1. Create a new project:

    ```bash
    composer create-project sylius/sylius-standard application
    ```

1. Configure the `.env`

    ```bash
    sed -i 's#DATABASE_URL.*#DATABASE_URL=postgresql://app:app@postgres:5432/app\?serverVersion=12\&charset=utf8#' application/.env
    ```

</details>

### How to add RabbitMQ and its dashboard

<details>

<summary>Read the cookbook</summary>

In order to use RabbitMQ and its dashboard, you should add a new service:

```Dockerfile
# services/rabbitmq/Dockerfile
FROM rabbitmq:3-management-alpine

COPY etc/. /etc/
```

And you can add specific RabbitMQ configuration in the `services/rabbitmq/etc/rabbitmq/rabbitmq.conf` file:
```
# services/rabbitmq/etc/rabbitmq/rabbitmq.conf
vm_memory_high_watermark.absolute = 1GB
```

Finally, add the following content to the `docker-compose.yml` file:
```yaml
volumes:
    rabbitmq-data: {}

services:
    rabbitmq:
        build: services/rabbitmq
        volumes:
            - rabbitmq-data:/var/lib/rabbitmq
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-rabbitmq.rule=Host(`rabbitmq.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-rabbitmq.tls=true"
            - "traefik.http.services.rabbitmq.loadbalancer.server.port=15672"
        profiles:
            - default
```

In order to publish and consume messages with PHP, you need to install the
`php${PHP_VERSION}-amqp` in the `php` image.

Then, you will be able to browse:

* `https://rabbitmq.<root_domain>` (username: `guest`, password: `guest`)

In your application, you can use the following configuration:

* host: `rabbitmq`;
* username: `guest`;
* password: `guest`;
* port: `rabbitmq`.

For example in Symfony you can use: `MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages`.

</details>

### How to add Redis and its dashboard

<details>

<summary>Read the cookbook</summary>

In order to use Redis and its dashboard, you should add the following content to
the `docker-compose.yml` file:

```yaml
volumes:
    redis-data: {}
    redis-insight-data: {}

services:
    redis:
        image: redis:5
        volumes:
            - "redis-data:/data"

    redis-insight:
        image: redislabs/redisinsight
        volumes:
            - "redis-insight-data:/db"
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-redis.rule=Host(`redis.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-redis.tls=true"
        profiles:
            - default

```

In order to communicate with Redis, you need to install the
`php${PHP_VERSION}-redis` in the `php` image.

Then, you will be able to browse:

* `https://redis.<root_domain>`

In your application, you can use the following configuration:

* host: `redis`;
* port: `6379`.

</details>

### How to add Maildev

<details>

<summary>Read the cookbook</summary>

In order to use Maildev and its dashboard, you should add the following content
to the `docker-compose.yml` file:

```yaml
services:
    maildev:
        image: maildev/maildev
        environment:
            - MAILDEV_WEB_PORT=80
            - MAILDEV_SMTP_PORT=25
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-maildev.rule=Host(`maildev.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-maildev.tls=true"
            - "traefik.http.services.maildev.loadbalancer.server.port=80"
        profiles:
            - default
```

Then, you will be able to browse:

* `https://maildev.<root_domain>`

In your application, you can use the following configuration:

* scheme: `smtp`;
* host: `maildev`;
* port: `25`.

For example in Symfony you can use: `MAILER_DSN=smtp://maildev:25`.

</details>

### How to add Mercure

<details>

<summary>Read the cookbook</summary>

In order to use Mercure, you should add the following content to the
`docker-compose.yml` file:

```yaml
services:
    mercure:
        image: dunglas/mercure
        environment:
            - "MERCURE_PUBLISHER_JWT_KEY=password"
            - "MERCURE_SUBSCRIBER_JWT_KEY=password"
            - "ALLOW_ANONYMOUS=1"
            - "CORS_ALLOWED_ORIGINS=*"
        labels:
            - "traefik.enable=true"
            - "project-name=${PROJECT_NAME}"
            - "traefik.http.routers.${PROJECT_NAME}-mercure.rule=Host(`mercure.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-mercure.tls=true"
        profiles:
            - default
```

If you are using Symfony, you must put the following configuration in the `.env` file:

```
MERCURE_PUBLISH_URL=http://mercure/.well-known/mercure
MERCURE_JWT_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InN1YnNjcmliZSI6W10sInB1Ymxpc2giOltdfX0.t9ZVMwTzmyjVs0u9s6MI7-oiXP-ywdihbAfPlghTBeQ
```

</details>

### How to add redirection.io

<details>

<summary>Read the cookbook</summary>

In order to use redirection.io, you should add the following content to the
`docker-compose.yml` file to run the agent:

```yaml
services:
    redirectionio-agent:
        build: services/redirectionio-agent
```

Add the following file `infrastructure/docker/services/redirectionio-agent/Dockerfile`:

```Dockerfile
FROM alpine:3.12 as alpine

WORKDIR /tmp

RUN apk add --no-cache wget ca-certificates \
    && wget https://packages.redirection.io/dist/stable/2/any/redirectionio-agent-latest_any_amd64.tar.gz \
    && tar -xzvf redirectionio-agent-latest_any_amd64.tar.gz

FROM scratch

# Binary copied from tar
COPY --from=alpine /tmp/redirection-agent/redirectionio-agent /usr/local/bin/redirectionio-agent

# Configuration, can be replaced by your own
COPY etc /etc

# Root SSL Certificates, needed as we do HTTPS requests to our service
COPY --from=alpine /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/

CMD ["/usr/local/bin/redirectionio-agent"]
```

Add `infrastructure/docker/services/redirectionio-agent/etc/redirectionio/agent.yml`:

```yaml
instance_name: "my-instance-dev" ### You may want to change this
listen: 0.0.0.0:10301
```

Then you'll need `wget`. In
`infrastructure/docker/services/php/Dockerfile`, in stage `frontend`:

```Dockerfile
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
```

You can group this command with another one.

Then, **after** installing nginx, you need to install the module:

```Dockerfile
RUN wget -q -O - https://packages.redirection.io/gpg.key | gpg --dearmor > /usr/share/keyrings/redirection.io.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/redirection.io.gpg] https://packages.redirection.io/deb/stable/2 focal main" | tee -a /etc/apt/sources.list.d/packages_redirection_io_deb.list \
    && apt-get update \
    && apt-get install libnginx-mod-redirectionio \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
```

Finally, you need to edit
`infrastructure/docker/services/php/frontend/etc/nginx/nginx.conf` to add the
following configuration in the `server` block:

```
redirectionio_pass redirectionio-agent:10301;
redirectionio_project_key "AAAAAAAAAAAAAAAA:BBBBBBBBBBBBBBBB";
```

**Don't forget to change the project key**.

</details>

### How to add Blackfire.io

<details>

<summary>Read the cookbook</summary>

In order to use Blackfire.io, you should add the following content to the
`docker-compose.yml` file to run the agent:

```yaml
services:
    blackfire:
        image: blackfire/blackfire
        environment:
            BLACKFIRE_SERVER_ID: FIXME
            BLACKFIRE_SERVER_TOKEN: FIXME
            BLACKFIRE_CLIENT_ID: FIXME
            BLACKFIRE_CLIENT_TOKEN: FIXME
        profiles:
            - default

```

Then you'll need `wget`. In
`infrastructure/docker/services/php/Dockerfile`, in stage `base`:

```Dockerfile
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        wget \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
```

You can group this command with another one.

Then, **after** installing PHP, you need to install the probe:

```Dockerfile
RUN wget -q -O - https://packages.blackfire.io/gpg.key | gpg --dearmor > /usr/share/keyrings/blackfire.io.gpg \
    && sh -c 'echo "deb [signed-by=/usr/share/keyrings/blackfire.io.gpg] http://packages.blackfire.io/debian any main" > /etc/apt/sources.list.d/blackfire.list' \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        blackfire-php \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && sed -i 's#blackfire.agent_socket.*#blackfire.agent_socket=tcp://blackfire:8707#' /etc/php/${PHP_VERSION}/mods-available/blackfire.ini
```

If you want to profile HTTP calls, you need to enable the probe with PHP-FPM.
So in `infrastructure/docker/services/php/Dockerfile`:

```Dockerfile
RUN phpenmod blackfire
```

Here also, You can group this command with another one.

</details>

### How to add support for crons?

<details>

<summary>Read the cookbook</summary>

In order to set up crontab, you should add a new container:

```Dockerfile
# services/php/Dockerfile

FROM php-base as cron

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY crontab /etc/cron.d/crontab
RUN crontab /etc/cron.d/crontab

CMD ["cron", "-f"]
```

And you can add all your crons in the `services/php/crontab` file:
```crontab
* * * * * su app -c "/usr/local/bin/php -r 'echo time().PHP_EOL;'" > /proc/1/fd/1 2>&1
```

Finally, add the following content to the `docker-compose.yml` file:
```yaml
services:
    cron:
        build:
            context: services/php
            target: cron
        volumes:
            - "../..:/var/www:cached"
        profiles:
            - default
```

</details>

### How to run workers?

<details>

<summary>Read the cookbook</summary>

In order to set up workers, you should define their services in the `docker-compose.worker.yml` file:

```yaml
services:
    worker_my_worker:
        <<: *worker_base
        command: /var/www/application/my-worker

    worker_date:
        <<: *worker_base
        command: watch -n 1 date
```

</details>

### How to use PHP FPM status page?

<details>

<summary>Read the cookbook</summary>

If you want to use the [PHP FPM status
page](https://www.php.net/manual/en/fpm.status.php) you need to remove a
configuration block in the
`infrastructure/docker/services/php/frontend/etc/nginx/nginx.conf` file:

```diff
-        # Remove this block if you want to access to PHP FPM monitoring
-        # dashboarsh (on URL: /php-fpm-status). WARNING: on production, you must
-        # secure this page (by user IP address, with a password, for example)
-        location ~ ^/php-fpm-status$ {
-            deny all;
-        }
-
```

And if your application uses the front controller pattern, and you want to see
the real request URI, you also need to uncomment the following configuration
block:

```diff
-            # # Uncomment if you want to use /php-fpm-status endpoint **with**
-            # # real request URI. It may have some side effects, that's why it's
-            # # commented by default
-            # fastcgi_param SCRIPT_NAME $request_uri;
+            # Uncomment if you want to use /php-fpm-status endpoint **with**
+            # real request URI. It may have some side effects, that's why it's
+            # commented by default
+            fastcgi_param SCRIPT_NAME $request_uri;
```

</details>

### How to pg_activity for monitoring PostgreSQL

<details>

<summary>Read the cookbook</summary>

In order to install pg_activity, you should add the following content to the
`infrastructure/docker/services/postgres/Dockerfile` file:

```Dockerfile
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        pg-activity \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
```

Then, you can add the following content to the `castor.php` file:

```php
#[AsTask(description: 'Monitor PostgreSQL', namespace: 'app:db')]
function pg_activity(): void
{
    docker_compose('exec postgres pg_activity -U app');
}
```

Finally you can use the following command:

```
castor app:db:pg-activity
```

</details>

### How to use MySQL instead of PostgreSQL

<details>

<summary>Read the cookbook</summary>

In order to use MySQL, you will need to apply this patch:

```diff
diff --git a/infrastructure/docker/docker-compose.builder.yml b/infrastructure/docker/docker-compose.builder.yml
index d00f315..bdfdc65 100644
--- a/infrastructure/docker/docker-compose.builder.yml
+++ b/infrastructure/docker/docker-compose.builder.yml
@@ -10,7 +10,7 @@ services:
     builder:
         build: services/builder
         depends_on:
-            - postgres
+            - mysql
         environment:
             - COMPOSER_MEMORY_LIMIT=-1
         volumes:
diff --git a/infrastructure/docker/docker-compose.worker.yml b/infrastructure/docker/docker-compose.worker.yml
index 2eda814..59f8fed 100644
--- a/infrastructure/docker/docker-compose.worker.yml
+++ b/infrastructure/docker/docker-compose.worker.yml
@@ -5,7 +5,7 @@ x-services-templates:
     worker_base: &worker_base
         build: services/worker
         depends_on:
-            - postgres
+            - mysql
             #- rabbitmq
         volumes:
             - "../..:/var/www:cached"
diff --git a/infrastructure/docker/docker-compose.yml b/infrastructure/docker/docker-compose.yml
index 49a2661..1804a01 100644
--- a/infrastructure/docker/docker-compose.yml
+++ b/infrastructure/docker/docker-compose.yml
@@ -1,7 +1,7 @@
 version: '3.7'

 volumes:
-    postgres-data: {}
+    mysql-data: {}

 services:
     router:
@@ -13,7 +13,7 @@ services:
     frontend:
         build: services/frontend
         depends_on:
-            - postgres
+            - mysql
         volumes:
             - "../..:/var/www:cached"
         labels:
@@ -24,10 +24,7 @@ services:
             # Comment the next line to be able to access frontend via HTTP instead of HTTPS
             - "traefik.http.routers.${PROJECT_NAME}-frontend-unsecure.middlewares=redirect-to-https@file"

-    postgres:
-        image: postgres:16
-        environment:
-            - POSTGRES_USER=app
-            - POSTGRES_PASSWORD=app
+    mysql:
+        image: mysql:8
+        environment:
+            - MYSQL_ALLOW_EMPTY_PASSWORD=1
         volumes:
-            - postgres-data:/var/lib/postgresql/data
+            - mysql-data:/var/lib/mysql
diff --git a/infrastructure/docker/services/php/Dockerfile b/infrastructure/docker/services/php/Dockerfile
index 56e1835..95fee78 100644
--- a/infrastructure/docker/services/php/Dockerfile
+++ b/infrastructure/docker/services/php/Dockerfile
@@ -24,7 +24,7 @@ RUN apk add --no-cache \
     php${PHP_VERSION}-intl \
     php${PHP_VERSION}-mbstring \
-    php${PHP_VERSION}-pgsql \
+    php${PHP_VERSION}-mysql \
     php${PHP_VERSION}-xml \
     php${PHP_VERSION}-zip \
```

</details>

### Docker For Windows support

<details>

<summary>Read the cookbook</summary>

This starter kit is compatible with Docker for Windows, so you can enjoy native Docker experience on Windows. You will have to keep in mind some differences:

- You will be prompted to run the env vars manually if you use PowerShell.
</details>

### How to access a container via hostname from another container

<details>

<summary>Read the cookbook</summary>

Let's say you have a container (`frontend`) that responds to many hostnames:
`app.test`, `api.app.test`, `admin.app.test`. And you have another container
(`builder`) that needs to call the `frontend` with a specific hostname - or with
HTTPS. This is usually the case when you have a functional test suite.

To enable this feature, you need to add `extra_hosts` to the `builder` container
like so:

```yaml
services:
    builder:
        # [...]
        extra_hosts:
            - "app.test:host-gateway"
            - "api.app.test:host-gateway"
            - "admin.app.test:host-gateway"
```

</details>

## Credits

- Created at [JoliCode](https://jolicode.com/)
- Logo by [Caneco](https://twitter.com/caneco)
