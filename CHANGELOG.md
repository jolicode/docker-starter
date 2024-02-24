# CHANGELOG

## 4.0.0 (not released yet)

* Upgrade Traefik from v2.7 to v3.0
* Migrate from Invoke to Castor
* Add a dockerfile linter
* Do not store certificates in the router image
* Upgrade to PostgreSQL v16
* Dynamically map user id and group id to the container, base on the host user
* Update maildev instructions
* Drop support for PHP < 8.1
* Add support for PHP 8.3
* Add some PHP tooling (PHP-CS-Fixer, PHPStan)
* Add `castor init` command to initialize a new project
* Add `castor symfony` to install a Symfony application
* Mount the project in `/var/www` instead of `/home/app`
* Update Composer to version 2.7
* Update NodeJS to version 20.x LTS
* Upgrade base to Debian Bookworm (12.5)

## 3.11.0 (2023-05-30)

* Use docker stages to build images

## 3.10.0 (2023-05-22)

* Fix workers detection in docker v23
* Update Invoke to version 2.1
* Update Composer to version 2.5.5
* Upgrade NodeJS to 18.x LTS version
* Migrate to Compose V2

## 3.9.0 (2022-12-21)

* Update documentation cookbook for installing redirection.io and Blackfire to
  remove deprecated apt-key usage
* Update Composer to version 2.5.0
* Increase the number of FPM worker from 4 to 25
* Enabled PHP FPM status page on `/php-fpm-status`
* Added support for PHP 8.2

## 3.8.0 (2022-06-15)

* Add documentation cookbook for using pg_activity
* Forward CI env vars in Docker containers
* Run the npm/yarn/webpack commands on the host for all mac users (even the ones not using Dinghy)
* Tests with PHP 7.4, 8.0, and 8.1

## 3.7.0 (2022-05-24)

* Add documentation cookbook for installing redirection.io
* Upgrade to Traefik v2.7.0
* Upgrade to PostgreSQL v14
* Upgrade to Composer v2.3

## 3.6.0 (2022-03-10)

* Upgrade NodeJS to version 16.x LTS and remove deprecated apt-key usage
* Various fix in the documentation
* Remove certificates when destroying infrastructure

## 3.5.0 (2022-01-27)

* Update PHP to version 8.1
* Generate SSL certificates with mkcert when available (self-signed otherwise)

## 3.4.0 (2021-10-13)

* Fix `COMPOSER_CACHE_DIR` default value when composer is not installed on host
* Upgrade base to Debian Bullseye (11.0)
* Document webpack 5+ integration

## 3.3.0 (2021-06-03)

* Update PHP to version 8.0
* Update Composer to version 2.1.0
* Fix APT key for Sury repository
* Fix the version of our debian base image

## 3.2.0 (2021-02-17)

* Migrate CI from Circle to GitHub Actions
* Add support for `docker-compose.override.yml`

## 3.1.0 (2020-11-13)

 * Fix TTY on all OS
 * Add default vendor installation command with auto-detection in `install()` for Yarn, NPM and Composer
 * Update Composer to version 2
 * Install by default php-uuid extension
 * Update NodeJS from 12.x to 14.x

## 3.0.0 (2020-07-01)

 * Migrate from Fabric to Invoke
 * Migrate from Alpine to Debian for PHP images
 * Add a confirmation when calling `inv destroy`
 * Tweak the PHP configuration
 * Upgrade PostgreSQL from 11 to 12
 * Upgrade Traefik from 2.0 to 2.2
 * Add an `help` task. This is the default one
 * The help command list all HTTP(s) services available
 * The help command list tasks available
 * Fix the support for Mac and Windows
 * Try to map the correct Composer cache dir from the host
 * Enhance the documentation

## 2.0.0 (2020-01-08)

* Better Docker for Windows support
* Add support for running many projects at the same time
* Upgrade Traefik from 1.7 to 2.0
* Add native support for workers

## 1.0.0 (2019-07-27)

* First release
