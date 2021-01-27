<p align="center">
    <img width="500" height="180" src="https://jolicode.com/media/original/docker-starter-logo.png" alt="Docker starter kit logo" />
</p>

# JoliCode's Docker starter kit

**WARNING**: You are reading the README of version 3 that uses [Invoke](https://www.pyinvoke.org/).

* If you are using [Fabric](https://www.fabfile.org/), you can read the [dedicated README](https://github.com/jolicode/docker-starter/tree/v2.0.0);

* If you want to migrate from docker-starter v2.x to v3.0, you can read the [dedicated guide](./UPGRADE-3.0.md);

## Introduction

Read [in English 🇬🇧](https://jolicode.com/blog/introducing-our-docker-starter-kit)
or [in French 🇫🇷](https://jolicode.com/blog/presentation-de-notre-starter-kit-docker)
why we created and open-sourced this starter-kit.

## Project configuration

Before executing any command, you need to configure few parameters in the
`invoke.py` file:

* `project_name` (**required**): This will be used to prefix all docker
objects (network, images, containers);

* `root_domain` (optional, default: `project_name + '.test'`): This is the
root domain where the application will be available;

* `extra_domains` (optional): This contains extra domains where the
application will be available;

* `project_directory` (optional, default: `application`): This is the host
directory containing your PHP application.

*Note*: Some Invoke tasks have been added for DX purposes. Checkout and adapt
the tasks `install`, `migrate` and `cache_clear` to your project.

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
using this stack and so add `/infrastructure/services/router/etc/ssl/certs/` to the
`.gitignore` file. See the [cookbook about mkcert usage](#how-to-generate-locally-trusted-ssl-certificates-with-mkcert).

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

Somes files will not be needed for your project and should be deleted:

```bash
rm -rf .circleci/ CHANGELOG.md CONTRIBUTING.md LICENSE UPGRADE-3.0.md
```

Also, in order to improve your usage of invoke scripts, you can install console autocompletion script.

If you are using bash:

```bash
invoke --print-completion-script=bash > /etc/bash_completion.d/invoke
```

If you are using something else, please refer to your shell documentation.
You may need to use `invoke --print-completion-script=zsh > /to/somewhere`.

Invoke supports completion for `bash`, `zsh` & `fish` shells.

## Cookbooks

### How to use with Symfony

<details>

<summary>Read the cookbook</summary>

If you want to create a new Symfony project, you need to enter a builder (`inv
builder`) and run the following commands

1. Remove the `application` folder:

    ```bash
    cd ..
    rm -rf application/*
    ```

1. Create a new project:

    ```bash
    composer create-project symfony/website-skeleton application
    ```

1. Configure the `.env`

    ```bash
    sed -i 's#DATABASE_URL.*#DATABASE_URL=postgresql://app:app@postgres:5432/app\?serverVersion=12\&charset=utf8#' application/.env
    ```

</details>

### How to use with Webpack Encore

<details>

<summary>Read the cookbook</summary>

If you want to use Webpack Encore in a Symfony project,

1. Follow [instructions on symfony.com](https://symfony.com/doc/current/frontend/encore/installation.html#installing-encore-in-symfony-applications) to install webpack encore.

    You will need to follow [these instructions](https://symfony.com/doc/current/frontend/encore/simple-example.html) too.

1. Create a new service for encore:

    Add the following content to the `docker-compose.yml` file:

    ```yaml
    services:
        encore:
            build: services/builder
            volumes:
                - "../../${PROJECT_DIRECTORY}:/home/app/application:cached"
            command: "yarn run dev-server --host 0.0.0.0 --port 9999 --hot --public https://encore.${PROJECT_ROOT_DOMAIN}/ --disable-host-check"
            labels:
                - "traefik.enable=true"
                - "traefik.http.routers.${PROJECT_NAME}-encore.rule=Host(`encore.${PROJECT_ROOT_DOMAIN}`)"
                - "traefik.http.routers.${PROJECT_NAME}-encore.tls=true"
                - "traefik.http.services.encore.loadbalancer.server.port=9999"
    ```

If the assets are not reachable, you may accept self signed certificate. To do so, open a new tab
at https://encore.app.test and click on accept.

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
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.rule=Host(`elasticsearch.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-elasticsearch.tls=true"

    kibana:
        image: kibana:7.8.0
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

In your application, you can use the following configuration:

* scheme: `http`;
* host: `elasticsearch`;
* port: `9200`.

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
`php${PHP_VERSION}-amqp` in the `php-base` image.

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
            - "traefik.http.routers.${PROJECT_NAME}-redis.rule=Host(`redis.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-redis.tls=true"

```

In order to communicate with Redis, you need to install the
`php${PHP_VERSION}-redis` in the `php-base` image.

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
            - "JWT_KEY=password"
            - "ALLOW_ANONYMOUS=1"
            - "CORS_ALLOWED_ORIGINS=*"
        labels:
            - "traefik.enable=true"
            - "traefik.http.routers.${PROJECT_NAME}-mercure.rule=Host(`mercure.${PROJECT_ROOT_DOMAIN}`)"
            - "traefik.http.routers.${PROJECT_NAME}-mercure.tls=true"
```

If you are using Symfony, you must put the following configuration in the `.env` file:

```
MERCURE_PUBLISH_URL=http://mercure/.well-known/mercure
MERCURE_JWT_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InN1YnNjcmliZSI6W10sInB1Ymxpc2giOltdfX0.t9ZVMwTzmyjVs0u9s6MI7-oiXP-ywdihbAfPlghTBeQ
```

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

```

Then you'll need `wget`. In
`infrastructure/docker/services/php-base/Dockerfile`:

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
RUN sh -c 'wget -q -O - https://packages.blackfire.io/gpg.key | apt-key add -' \
    && sh -c 'echo "deb http://packages.blackfire.io/debian any main" > /etc/apt/sources.list.d/blackfire.list' \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        blackfire-php \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && sed -i 's#blackfire.agent_socket.*#blackfire.agent_socket=tcp://blackfire:8707#' /etc/php/${PHP_VERSION}/mods-available/blackfire.ini
```

If you want to profile HTTP calls, you need to enable the probe with PHP-FPM.
So in `infrastructure/docker/services/frontend/Dockerfile`:

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
# services/cron/Dockerfile
ARG PROJECT_NAME

FROM ${PROJECT_NAME}_php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

COPY crontab /etc/cron.d/crontab
RUN crontab /etc/cron.d/crontab

CMD ["cron", "-f"]
```

And you can add all your crons in the `services/cron/crontab` file:
```crontab
* * * * * su app -c "/usr/local/bin/php -r 'echo time().PHP_EOL;'" > /proc/1/fd/1 2>&1
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

In order to set up workers, you should define their service in the `docker-compose.worker.yml` file:

```yaml
services:
    worker_my_worker:
        <<: *worker_base
        command: /home/app/application/my-worker

    worker_date:
        <<: *worker_base
        command: watch -n 1 date
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
@@ -24,7 +24,7 @@ RUN apk add --no-cache \
     php${PHP_VERSION}-intl \
     php${PHP_VERSION}-mbstring \
-    php${PHP_VERSION}-pgsql \
+    php${PHP_VERSION}-mysql \
     php${PHP_VERSION}-xml \
     php${PHP_VERSION}-zip \
diff --git a/infrastructure/docker/services/postgres/Dockerfile b/infrastructure/docker/services/postgres/Dockerfile
deleted file mode 100644
index a1c26c4..0000000
--- a/infrastructure/docker/services/postgres/Dockerfile
+++ /dev/null
@@ -1,3 +0,0 @@
-FROM postgres:12
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
in the `invoke.py` file. This will force docker-compose to build these
services first.

</details>

### Docker For Windows support

<details>

<summary>Read the cookbook</summary>

This starter kit is compatible with Docker for Windows, so you can enjoy native Docker experience on Windows. You will have to keep in mind some differences:

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
environment variables you may script this with Invoke.

</details>

### How to generate locally trusted SSL certificates with mkcert

<details>

<summary>Read the cookbook</summary>

First, you need to remove the existing self-signed certificates and ignore the
directory from git:

```bash
git rm infrastructure/docker/services/router/etc/ssl/certs/*.pem
touch infrastructure/docker/services/router/etc/ssl/certs/.gitkeep
echo '/infrastructure/docker/services/router/etc/ssl/certs/*.pem' >> .gitignore
```

Then you need to [install mkcert](https://github.com/FiloSottile/mkcert#installation)
and setup its root CA on your computer. Everyone that want to use your project
will need to setup mkcert once if they never did it before:

```bash
mkcert -install
```

You can now generate trusted SSL certificates for your project. To ease the process
for everyone, you can add this Invoke task:

```python
from distutils.spawn import find_executable


@task
def generate_certificates(c):
    """
    Generate SSL certificate with mkcert
    """
    with Builder(c):
        if find_executable('mkcert') is None:
            print(Fore.RED + 'You must install mkcert first')
            return

        path_caroot = c.run('mkcert -CAROOT', hide=True).stdout.strip()

        if not os.path.isdir(path_caroot):
            print(Fore.RED + 'You must have mkcert CA Root installed on your host with `mkcert -install` command')
            return

        c.run('mkcert -cert-file infrastructure/docker/services/router/etc/ssl/certs/cert.pem -key-file infrastructure/docker/services/router/etc/ssl/certs/key.pem %s "*.%s"' % (c.root_domain, c.root_domain))
        print(Fore.GREEN + 'SSL certificate is now installed!')
```

Finally, run this new command, rebuild the infrastructure (with `inv up` or `inv start`)
and your project will now run with locally trusted certificates.

Ideally you will document this process in your project's README:

> ### SSL certificates (first time only)
>
> Before running the application for the first time, you also need to generate SSL certificates
> to make the application working in HTTPS out of the box.
>
> We suggest to use [`mkcert`](https://github.com/FiloSottile/mkcert#installation) to generate and sign your certificates.
>
> If you never installed CA root from mkcert before, you can do it by running `mkcert -install`. Now you can run the following
> command to generate the SSL certificates for the project:
>
> ```bash
>  inv generate-certificates
> ```

</details>

## Credits

- Created at [JoliCode](https://jolicode.com/)
- Logo by [Caneco](https://twitter.com/caneco)
