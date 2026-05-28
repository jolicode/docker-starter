<?php

use Castor\Attribute\AsTask;

use function Castor\fs;
use function Castor\io;
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

    if (false === $readMeContent) {
        return;
    }

    $urls = [variable('root_domain'), ...variable('extra_domains')];
    $readMeContent = str_replace('<your hostnames>', implode(' ', $urls), $readMeContent);
    file_put_contents('README.md', $readMeContent);
}

#[AsTask(description: 'Install Symfony')]
function symfony(bool $webApp = false): void
{
    $base = rtrim(variable('root_dir') . '/application');

    $gitIgnore = $base . '/.gitignore';
    $gitIgnoreContent = '';
    if (file_exists($gitIgnore)) {
        $gitIgnoreContent = file_get_contents($gitIgnore);
    }

    build();
    docker_compose_run('composer create-project symfony/skeleton sf');

    fs()->mirror($base . '/sf/', $base, options: ['override' => true]);
    fs()->remove([$base . '/sf', $base . '/var']);

    if ($webApp) {
        docker_compose_run('composer require webapp');
    }

    docker_compose_run("sed -i 's#^DATABASE_URL.*#DATABASE_URL=postgresql://app:app@postgres:5432/app\\?serverVersion=16\\&charset=utf8#' .env");
    file_put_contents($gitIgnore, $gitIgnoreContent, \FILE_APPEND);
}

#[AsTask(description: 'Install Sylius')]
function sylius(): void
{
    $base = rtrim(variable('root_dir') . '/application');

    $gitIgnore = $base . '/.gitignore';
    $gitIgnoreContent = '';
    if (file_exists($gitIgnore)) {
        $gitIgnoreContent = file_get_contents($gitIgnore);
    }

    build();
    docker_compose_run('composer create-project sylius/sylius-standard sylius');

    fs()->mirror($base . '/sylius/', $base, options: ['override' => true]);
    fs()->remove([$base . '/sylius', $base . '/var']);

    docker_compose_run("sed -i 's#^DATABASE_URL.*#DATABASE_URL=postgresql://app:app@postgres:5432/app\\?serverVersion=16\\&charset=utf8#' .env");
    file_put_contents($gitIgnore, $gitIgnoreContent, \FILE_APPEND);

    chrome();
}

#[AsTask(description: 'Add the Browserless Chrome service to docker-compose.dev.yml')]
function chrome(): void
{
    $base = rtrim(variable('root_dir') . '/infrastructure/docker');

    $file = $base . '/docker-compose.dev.yml';

    if (!file_exists($file)) {
        io()->error(sprintf('File "%s" not found.', $file));

        return;
    }

    $content = file_get_contents($file);

    if (false === $content) {
        io()->error(sprintf('Unable to read "%s".', $file));

        return;
    }

    if (str_contains($content, "\n    chrome:")) {
        io()->warning('The chrome service already exists.');

        return;
    }

    $chromeService = <<<'YAML'

            chrome:
                image: ghcr.io/browserless/chromium
                ports:
                    - "9222:3000"
        YAML;

    /*
     * Insert the Chrome service before the "volumes:" section if it exists.
     * Otherwise, append it to the end of the file.
     */
    if (preg_match('/^volumes:/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $position = $matches[0][1];

        $content = substr_replace(
            $content,
            $chromeService . "\n",
            $position,
            0
        );
    } else {
        $content .= $chromeService . PHP_EOL;
    }

    file_put_contents($file, $content);

    io()->success('Chrome service successfully added to docker-compose.dev.yml');
}
