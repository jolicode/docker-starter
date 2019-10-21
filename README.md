<p align="center">
    <img width="500" height="180" src="/art/logo.png" alt="Docker starter kit logo" />
</p>

# JoliCode's Docker starter kit

## Before using the stack (remove this chapter once done)

### Introduction

Read [in English 🇬🇧](https://jolicode.com/blog/introducing-our-docker-starter-kit)
or [in French 🇫🇷](https://jolicode.com/blog/presentation-de-notre-starter-kit-docker)
why we created and open-sourced this starter-kit.

### Project configuration

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

### SSL certificate

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

---

## Running the application locally

### Requirements

A Docker environment is provided and requires you to have these tools available:

 * Docker
 * pipenv (see [these instructions](https://pipenv.readthedocs.io/en/latest/install/) for how to install)

Install and run `pipenv` to install the required tools:

```bash
pipenv install
```

You can configure your current shell to be able to use fabric commands directly
(without having to prefix everything by `pipenv run`)

```bash
pipenv shell
```

### Docker environment

The Docker infrastructure provides a web stack with:
 - NGINX
 - PostgreSQL
 - PHP
 - Traefik
 - A container with some tooling:
   - Composer
   - Node
   - Yarn / NPM

### Domain configuration (first time only)

Before running the application for the first time, ensure your domain names
point the IP of your Docker daemon by editing your `/etc/hosts` file.

This IP is probably `127.0.0.1` unless you run Docker in a special VM (docker-machine, dinghy, etc).

Note: The router binds port 80 and 443, that's why it will work with `127.0.0.1`

```
echo '127.0.0.1 <your hostnames>' | sudo tee -a /etc/hosts
```

Using dinghy? Run `dinghy ip` to get the IP of the VM.

### Starting the stack

Launch the stack by running this command:

```bash
fab start
```

> Note: the first start of the stack should take a few minutes.

The site is now accessible at the hostnames your have configured over HTTPS
(you may need to accept self-signed SSL certificate).

### Builder

Having some composer, yarn or another modifications to make on the project?
Start the builder which will give you access to a container with all these
tools available:

```bash
fab builder
```

Note: You can add as many fabric command as you want. But the command should be
ran by the builder, don't forget to add `@with_builder` annotation to the
function.

### Other tasks

Checkout `fab -l` to have the list of available fabric tasks.

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
    sed -i "s#DATABASE_URL.*#DATABASE_URL=pgsql://app:app@postgres/YOUR_DB_NAME#" app/.env
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

### How to use MySQL instead of PostgreSQL

<details>

<summary>Read the cookbook</summary>

In order to use MySQL, you will need to revert this diff:

```diff
diff --git a/infrastructure/docker/docker-compose.builder.yml b/infrastructure/docker/docker-compose.builder.yml
index 39198ca..dc5fce1 100644
--- a/infrastructure/docker/docker-compose.builder.yml
+++ b/infrastructure/docker/docker-compose.builder.yml
@@ -10,7 +10,7 @@ services:
     builder:
         build: services/builder
         depends_on:
-            - mysql
+            - postgres
         volumes:
             - "../../${PROJECT_DIRECTORY}:/home/app/application:cached"
             - "~/.composer/cache:/home/app/.composer/cache"
diff --git a/infrastructure/docker/docker-compose.yml b/infrastructure/docker/docker-compose.yml
index 0dffe3a..0ae36cd 100644
--- a/infrastructure/docker/docker-compose.yml
+++ b/infrastructure/docker/docker-compose.yml
@@ -1,7 +1,7 @@
 version: '3'

 volumes:
-    mysql-data: {}
+    postgres-data: {}

 services:
     router:
@@ -24,7 +24,7 @@ services:
     frontend:
         build: services/frontend
         depends_on:
-            - mysql
+            - postgres
         volumes:
             - "../../${PROJECT_DIRECTORY}:/var/www:cached"
         labels:
@@ -32,11 +32,12 @@ services:
             - "traefik.frontend.entryPoints=https"
             - "traefik.frontend.rule=Host:${PROJECT_HOSTNAMES}"

-    mysql:
-        build: services/mysql
+    postgres:
+        build: services/postgres
+        environment:
+            - POSTGRES_USER=app
+            - POSTGRES_PASSWORD=app
         volumes:
-            - "mysql-data:/var/lib/mysql"
+            - postgres-data:/var/lib/postgresql/data
         labels:
             - "traefik.enable=false"
-        ports:
-          - "3306:3306"
diff --git a/infrastructure/docker/services/builder/Dockerfile b/infrastructure/docker/services/builder/Dockerfile
index 173c1a6..87424f7 100644
--- a/infrastructure/docker/services/builder/Dockerfile
+++ b/infrastructure/docker/services/builder/Dockerfile
@@ -7,7 +7,6 @@ RUN apk add --no-cache \
     g++ \
     git \
     make \
-    mariadb-client \
     nodejs \
     npm \
     php7-phar \
diff --git a/infrastructure/docker/services/mysql/Dockerfile b/infrastructure/docker/services/mysql/Dockerfile
deleted file mode 100644
index e9e0245..0000000
--- a/infrastructure/docker/services/mysql/Dockerfile
+++ /dev/null
@@ -1,3 +0,0 @@
-FROM mariadb:10.4
-
-ENV MYSQL_ALLOW_EMPTY_PASSWORD=1
diff --git a/infrastructure/docker/services/php-base/Dockerfile b/infrastructure/docker/services/php-base/Dockerfile
index ea6fc5e..316cbde 100644
--- a/infrastructure/docker/services/php-base/Dockerfile
+++ b/infrastructure/docker/services/php-base/Dockerfile
@@ -22,7 +22,7 @@ RUN apk add --no-cache \
     php7-opcache \
     php7-openssl \
     php7-pdo \
-    php7-pdo_mysql \
+    php7-pdo_pgsql \
     php7-pcntl \
     php7-posix \
     php7-session \
diff --git a/infrastructure/docker/services/postgres/Dockerfile b/infrastructure/docker/services/postgres/Dockerfile
new file mode 100644
index 0000000..998fda8
--- /dev/null
+++ b/infrastructure/docker/services/postgres/Dockerfile
@@ -0,0 +1 @@
+FROM postgres:11
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


### Credits

- Created by (Gregoire Pineau)[https://twitter.com/lyrixx]
- Logo by (Caneco)[https://twitter.com/caneco]
