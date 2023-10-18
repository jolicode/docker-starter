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

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(): void
{
    docker_compose_run('phpstan --configuration=/home/app/root/phpstan.neon', workDir: '/home/app/application');
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): void
{
    if ($dryRun) {
        docker_compose_run('php-cs-fixer fix --dry-run --diff', workDir: '/home/app/root');
    } else {
        docker_compose_run('php-cs-fixer fix', workDir: '/home/app/root');
    }
}
