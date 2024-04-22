<?php

namespace qa;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\variable;
use function docker\docker_compose_run;
use function docker\docker_exit_code;

#[AsTask(description: 'Runs all QA tasks')]
function all(): int
{
    install();
    $cs = cs();
    $phpstan = phpstan();
    // $phpunit = phpunit();

    return max($cs, $phpstan/* , $phpunit */);
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    io()->title('Installing QA tooling');

    docker_compose_run('composer install -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer install -o', workDir: '/var/www/tools/phpstan');
}

// #[AsTask(description: 'Runs PHPUnit', aliases: ['phpunit'])]
// function phpunit(): int
// {
//     return docker_exit_code('phpunit');
// }

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(): int
{
    if (!is_dir(variable('root_dir') . '/tools/phpstan/vendor')) {
        io()->error('PHPStan is not installed. Run `castor qa:install` first.');

        return 1;
    }

    return docker_exit_code('phpstan', workDir: '/var/www');
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    if (!is_dir(variable('root_dir') . '/tools/php-cs-fixer/vendor')) {
        io()->error('PHP-CS-Fixer is not installed. Run `castor qa:install` first.');

        return 1;
    }

    if ($dryRun) {
        return docker_exit_code('php-cs-fixer fix --dry-run --diff', workDir: '/var/www');
    }

    return docker_exit_code('php-cs-fixer fix', workDir: '/var/www');
}
