<?php

use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\variable;
use function docker\build;
use function docker\docker_compose_run;

#[AsTask(description: 'Initialize the project')]
function init(): void
{
    fs()->remove([
        '.github/',
        'README.md',
        'CHANGELOG.md',
        'CONTRIBUTING.md',
        'LICENSE',
        __FILE__,
    ]);
    fs()->rename('README.dist.md', 'README.md');

    $readMeContent = file_get_contents('README.md');
    $urls = [variable('root_domain'), ...variable('extra_domains')];
    $readMeContent = str_replace('<your hostnames>', implode(' ', $urls), $readMeContent);
    file_put_contents('README.md', $readMeContent);
}

#[AsTask(description: 'Install Symfony')]
function symfony(bool $webApp = false): void
{
    $base = rtrim(variable('root_dir') . '/' . variable('project_directory'), '/');

    $gitIgnore = $base . '/.gitignore';
    $gitIgnoreContent = '';
    if (file_exists($gitIgnore)) {
        $gitIgnoreContent = file_get_contents($gitIgnore);
    }

    build();
    docker_compose_run('composer create-project symfony/skeleton sf');

    fs()->mirror($base . '/sf/', $base);

    if ($webApp) {
        docker_compose_run('composer require webapp');
    }

    docker_compose_run("sed -i 's#^DATABASE_URL.*#DATABASE_URL=postgresql://app:app@postgres:5432/app\\?serverVersion=16\\&charset=utf8#' .env");
    file_put_contents($gitIgnore, $gitIgnoreContent, \FILE_APPEND);

    fs()->remove($base . '/sf');
}
