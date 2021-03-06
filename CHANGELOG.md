# CHANGELOG

## x.x.x (not released yet)

* Update Composer to version 2.0.11
* Fixed apt key for Sury repository

## 3.2.0 (2021-02-17)

* Migrate CI from Circle to GitHub Actions
* Add support for `docker-compose.override.yml`

## 3.1.0 (2020-11-13)

 * Fixed TTY on all OS
 * Added default vendor installation command with auto-detection in `install()` for Yarn, NPM and Composer
 * Update Composer to version 2
 * Install by default php-uuid extension
 * Update NodeJS from 12.x to 14.x

## 3.0.0 (2020-07-01)

 * Migrate from Fabric to Invoke
 * Migrate from Alpine to Debian for PHP images
 * Add a confirmation when calling `inv destroy`
 * Tweaked the PHP configuration
 * Upgraded PostgreSQL from 11 to 12
 * Upgraded Traefik from 2.0 to 2.2
 * Added an `help` task. This is the default one
 * The help command list all HTTP(s) services available
 * The help command list tasks available
 * Fixed the support for Mac and Windows
 * Try to map the correct Composer cache dir from the host
 * Enhance the documentation

## 2.0.0 (2020-01-08)

* Better Docker for Windows support
* Add support for running many projects at the same time
* Upgraded Traefik from 1.7 to 2.0
* Add native support for workers

## 1.0.0 (2019-07-27)

* First release
