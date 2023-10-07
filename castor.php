<?php

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;

import(__DIR__ . '/.castor');

/**
 * @return array<string, mixed>
 */
function create_default_variables(): array
{
    $projectName = 'app';
    $tld = 'test';

    return [
        'project_name' => $projectName,
        'root_domain' => "{$projectName}.{$tld}",
        'extra_domains' => [
            "www.{$projectName}.{$tld}",
        ],
        'php_version' => $_SERVER['DS_PHP_VERSION'] ?? '8.2',
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    infra\workers_stop();
    infra\generate_certificates(false);
    infra\build();
    infra\up();
    cache_clear();
    install();
    migrate();
    infra\workers_start();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app')]
function install(): void
{
    $rootDir = variable('root_dir');

    if (is_file("{$rootDir}/composer.json")) {
        docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');
    }
    if (is_file("{$rootDir}/yarn.lock")) {
        docker_compose_run('yarn');
    } elseif (is_file("{$rootDir}/package.json")) {
        docker_compose_run('npm install');
    }

    qa\install();
}

#[AsTask(description: 'Clear the application cache', namespace: 'app')]
function cache_clear(): void
{
    // docker_compose_run('rm -rf var/cache/ && bin/console cache:warmup');
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db')]
function migrate(): void
{
    // docker_compose_run('bin/console doctrine:database:create --if-not-exists');
    // docker_compose_run('bin/console doctrine:migration:migrate -n --allow-no-migration');
}
