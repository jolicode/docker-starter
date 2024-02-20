<?php

namespace qa;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Runs all QA tasks')]
function all(): int
{
    install();
    $cs = cs();
    $phpstan = phpstan();

    return max($cs, $phpstan);
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    docker_compose_run('composer install -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer install -o', workDir: '/var/www/tools/phpstan');
}

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(): int
{
    return docker_exit_code('phpstan');
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    if ($dryRun) {
        return docker_exit_code('php-cs-fixer fix --dry-run --diff');
    }

    return docker_exit_code('php-cs-fixer fix');
}
