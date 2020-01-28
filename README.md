<p align="center">
    <img width="500" height="180" src="https://jolicode.com/media/original/docker-starter-logo.png" alt="Docker starter kit logo" />
</p>

# JoliCode's Docker starter kit

## Introduction

Read [in English 🇬🇧](https://jolicode.com/blog/introducing-our-docker-starter-kit)
or [in French 🇫🇷](https://jolicode.com/blog/presentation-de-notre-starter-kit-docker)
why we created and open-sourced this starter-kit.

## Project configuration

Before executing any command, you need to configure few parameters in the
`fabfile.py` file:

* `env.project_name` (**required**): This will be used to prefix all docker
objects (network, images, containers);

* `env.root_domain` (optional, default: `project_name + '.test'`): This is the
root domain where the application will be available;

* `env.extra_domains` (optional): This contains extra domains where the
application will be available;

* `env.project_directory` (optional, default: `application`): This is the host
directory containing your PHP application.

*Note*: Some Fabric tasks have been added for DX purposes. Checkout and adapt
the tasks `install`, `migrate` and `cache_clear` to your project

## SSL certificate

To save your time with certificate generation, this project already embed a
basic self-signed certificate. So *HTTPS will work out of the box* in your browser
as soon as you accept this self-signed certificate.

However, if you prefer to have valid certificate in local (some tools do not
necessarily let you work with invalid certificates), you will have to:
- generate a certificate valid for your domain name
- sign this certificate with a locally trusted CA

In this case, it's recommended to use more powerful tool like [mkcert](https://github.com/FiloSottile/mkcert).
As mkcert uses a CA root, you will need to generate a certificate on each computer
using this stack and so add `/infrastructure/services/router/certs/` to the
`.gitignore` file.

Alternatively, you can configure
`infrastructure/docker/services/router/openssl.cnf` then use
`infrastructure/docker/services/router/generate-ssl.sh` to create your own
certificate. Then you will have to add it to your computer CA store.

## Usage documentation

We provide a [README.dist.md](./README.dist.md) to explain what anyone need
to know to start and interact with the infrastructure.

You should probably use this README.dist.md as a base for your project's README.md:

```bash
mv README.{dist.md,md}
```

## Cookbooks

### How to use with Symfony

<details>

<summary>Read the cookbook</summary>

If you want to create a new Symfony project, you need to:

1. Remove the `application` folder:

    ```bash
    rm -rf application/
    ```

1. Create a new project:

    ```bash
    composer create-project symfony/website-skeleton application
    ```

1. Configure the `.env`

    ```bash
    sed -i "s#DATABASE_URL.*#DATABASE_URL=pgsql://app:app@postgres/YOUR_DB_NAME#" application/.env
    ```

1. Configure doctrine

    By default, Symfony and Doctrine are configured to use MySQL. Since MySQL
    has bad default configuration, Doctrine is forced to configure MySQL
    explicitly. PostgreSQL does not have this issue. So **update the following
    configuration** in `application/config/packages/doctrine.yaml`:

    ```yaml
    doctrine:
        dbal:
            # configure these for your database server
            driver: 'pdo_pgsql'
            server_version: '11'
            charset: UTF8
            default_table_options:
                charset: UTF8
                # Adapt the collate according to the content of your DB.
                # For example, if your content is mainly in French:
                # collate: fr_FR.UTF8

            url: '%env(resolve:DATABASE_URL)%'
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
        image: elasticsearch:7.3.2
        volumes:
            - elasticsearch-data:/usr/share/elasticsearch/data
        environment:
            - "ES_JAVA_OPTS=-Xms128m -Xmx128m"
            - "discovery.type=single-node"
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.rule=Host(`elasticsearch.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.tls=true"

    kibana:
        image: kibana:7.3.2
        depends_on:
            - elasticsearch
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.${PROJECT_NAME}-kibana.rule=Host(`kibana.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-kibana.tls=true"
```

Then, you will be able to browse:

* `https://kibana.<root_domain>`
* `https://elasticsearch.<root_domain>`

</details>

### How to add RabbitMQ and its dashboard

<details>

<summary>Read the cookbook</summary>

In order to use RabbitMQ and its dashboard, you should add the following content
to the `docker-compose.yml` file:

```yaml
volumes:
    rabbitmq-data: {}

services:
    rabbitmq:
        image: rabbitmq:3-management-alpine
        volumes:
            - rabbitmq-data:/var/lib/rabbitmq
        environment:
            - "RABBITMQ_VM_MEMORY_HIGH_WATERMARK=1024MiB"
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.${PROJECT_NAME}-rabbitmq.rule=Host(`rabbitmq.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-rabbitmq.tls=true"
            - "traefik.http.services.rabbitmq.loadbalancer.server.port=15672"
```

In order to publish and consume messages with PHP, you need to install the
`php7-amqp` in the `php-base` image.

Then, you will be able to browse:

* `https://rabbitmq.<root_domain>`

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
            - "traefik.http.routers.${PROJECT_NAME}-redis.rule=Host(`redis.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-redis.tls=true"

```

Then, you will be able to browse:

* `https://redis.<root_domain>`

</details>

### How to add Maildev

<details>

<summary>Read the cookbook</summary>

In order to use Maildev and its dashboard, you should add the following content
to the `docker-compose.yml` file:

```yaml
services:
    maildev:
        image: djfarrelly/maildev
        command: ["bin/maildev", "--web", "80", "--smtp", "25", "--hide-extensions", "STARTTLS"]
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.${PROJECT_NAME}-maildev.rule=Host(`maildev.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-maildev.tls=true"
            - "traefik.http.services.maildev.loadbalancer.server.port=80"
```

Then, you will be able to browse:

* `https://maildev.<root_domain>`

</details>

### How to add support for crons?

<details>

<summary>Read the cookbook</summary>

In order to setup crontab, you should add a new container:

```Dockerfile
# services/cron/Dockerfile
ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

COPY crontab /etc/crontabs/app

CMD ["crond", "-l", "0", "-f"]
```

And you can add all your crons in the `services/cron/crontab` file:
```crontab
* * * * * php /home/app/application/my-command >> /path/to/log
```

Finally, add the following content to the `docker-compose.yml` file:
```yaml
services:
    cron:
        build: services/cron
        volumes:
            - "../../${PROJECT_DIRECTORY}:/home/app/application:cached"
```

</details>

### How to run workers?

<details>

<summary>Read the cookbook</summary>

In order to setup workers, you should define their service in the `docker-compose.worker.yml` file:

```yaml
services:
    worker_my_worker:
        <<: *worker_base
        command: /home/app/application/my-worker

    worker_date:
        <<: *worker_base
        command: watch -n 1 date
```

You also need to fill the `fabfile.py` to fill the tasks `start_workers` and `stop_workers`.
These tasks currently propose default examples to use with Symfony Messenger.

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
             - "../../${PROJECT_DIRECTORY}:/home/app/application:cached"
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
             - "../../${PROJECT_DIRECTORY}:/home/app/application:cached"
         labels:
@@ -24,10 +24,7 @@ services:
             # Comment the next line to be able to access frontend via HTTP instead of HTTPS
             - "traefik.http.routers.${PROJECT_NAME}-frontend-unsecure.middlewares=redirect-to-https@file"
 
-    postgres:
-        build: services/postgres
-        environment:
-            - POSTGRES_USER=app
-            - POSTGRES_PASSWORD=app
+    mysql:
+        build: services/mysql
         volumes:
-            - postgres-data:/var/lib/postgresql/data
+            - mysql-data:/var/lib/mysql
diff --git a/infrastructure/docker/services/mysql/Dockerfile b/infrastructure/docker/services/mysql/Dockerfile
new file mode 100644
index 0000000..e9e0245
--- /dev/null
+++ b/infrastructure/docker/services/mysql/Dockerfile
@@ -0,0 +1,3 @@
+FROM mariadb:10.4
+
+ENV MYSQL_ALLOW_EMPTY_PASSWORD=1
diff --git a/infrastructure/docker/services/php-base/Dockerfile b/infrastructure/docker/services/php-base/Dockerfile
index 56e1835..95fee78 100644
--- a/infrastructure/docker/services/php-base/Dockerfile
+++ b/infrastructure/docker/services/php-base/Dockerfile
@@ -22,7 +22,7 @@ RUN apk add --no-cache \
     php7-opcache \
     php7-openssl \
     php7-pdo \
-    php7-pdo_pgsql \
+    php7-pdo_mysql \
     php7-pcntl \
     php7-posix \
     php7-session \
diff --git a/infrastructure/docker/services/postgres/Dockerfile b/infrastructure/docker/services/postgres/Dockerfile
deleted file mode 100644
index a1c26c4..0000000
--- a/infrastructure/docker/services/postgres/Dockerfile
+++ /dev/null
@@ -1,3 +0,0 @@
-FROM postgres:11
-
-EXPOSE 5432
```

</details>

### How to solves build dependencies

<details>

<summary>Read the cookbook</summary>

Docker-compose is not a tool to build images. This is why you can hit the
following bug:

> ERROR: Service 'frontend' failed to build: pull access denied for app_basephp, repository does not exist or may require 'docker login': denied: requested access to the resource is denied

In order to fix this issue, you can update the `services_to_build_first` variable
in the `fabfile.py` file. This will force docker-compose to build theses
services first.

</details>

### Windows support (experimental)

<details>

<summary>Read the cookbook</summary>

This starter kit is compatible with Docker for Windows, so you can enjoy native Docker experience on Windows. You will have to keep in mind some differences:

- Composer cache can't be set to the relative home path in `infrastructure/docker/docker-compose.builder.yml`: remove `- "~/.composer/cache:/home/app/.composer/cache"`;
- Python 2.7.17 is broken, do not use it: https://github.com/pypa/pipenv/issues/4016;
- You will be prompted to run the env vars manually if you use PowerShell.

</details>

### How to access a container via a custom hostname from another container

<details>

<summary>Read the cookbook</summary>

Let's say you have a container (`frontend`) that responds to many hostname:
`app.test`, `api.app.test`, `admin.app.test`. And you have another container
(`builder`) that need to call the `frontend` with a specific hostname - or with
HTTPS. This is usually the case when you have a functional test suite.

To enable this feature, you need to add `extra_hosts` to the `builder` container
like following:

```yaml
services:
    builder:
        # [...]
        extra_hosts:
            - "app.test:172.17.0.1"
            - "api.app.test:172.17.0.1"
            - "admin.app.test:172.17.0.1"
```

Note: `172.17.0.1` is the default IP of the `docker0` interface. It can be
different on some installations. You can see this IP thanks to the following
command `ip address show docker0`. Since `docker-compose.yml` file supports
environnement variables you may script this with fabric.

</details>

## Credits

- Created at [JoliCode](https://jolicode.com/)
- Logo by [Caneco](https://twitter.com/caneco)
