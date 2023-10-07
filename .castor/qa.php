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
    docker_compose_run('composer install -o --working-dir=tools/php-cs-fixer');
    docker_compose_run('composer install -o --working-dir=tools/phpstan');
}

#[AsTask(description: 'Runs PHPStan')]
function phpstan(): void
{
    docker_compose_run('phpstan');
}

#[AsTask(description: 'Fixes Coding Style')]
function cs(bool $dryRun = false): void
{
    if ($dryRun) {
        docker_compose_run('php-cs-fixer fix --dry-run --diff');
    } else {
        docker_compose_run('php-cs-fixer fix');
    }
}
