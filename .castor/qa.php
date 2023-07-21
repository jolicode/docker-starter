<?php

namespace qa;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Runs all QA tasks')]
function all(): void
{
    install();
    cs();
    phpstan();
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    docker_compose_run('composer install -o', workDir: '/home/app/root/tools/php-cs-fixer');
    docker_compose_run('composer install -o', workDir: '/home/app/root/tools/phpstan');
}

#[AsTask(description: 'Runs PHPStan')]
function phpstan(): void
{
    docker_compose_run('phpstan', workDir: '/home/app/root');
}

#[AsTask(description: 'Fixes Coding Style')]
function cs(bool $dryRun = false): void
{
    if ($dryRun) {
        docker_compose_run('php-cs-fixer fix --dry-run --diff', workDir: '/home/app/root');
    } else {
        docker_compose_run('php-cs-fixer fix', workDir: '/home/app/root');
    }
}
