<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Castor\Context;
use function Castor\{exec, parallel};

#[AsContext(default: true)]
function __default(): Context
{
    $projectName = 'app';
    $context = new Context([
        'project_name' => $projectName,
        'project_directory' => 'application',
        'root_domain' => $projectName . '.local',
        'extra_domains' => [
            'www.' . $projectName . '.local',
        ],
        'php_version' => '8.1',
        'docker_compose_files' => [
            'docker-compose.yml',
            'docker-compose.worker.yml',
            'docker-compose.builder.yml',
        ],
        'services_to_build_first' => [
            'php-base',
            'builder',
        ],
        'dinghy' => false,
        'macos' => false,
        'power_shell' => false,
        'user_id' => 1000,
        'root_dir' => __DIR__,
        'start_workers' => false,
        'composer_cache_dir' => '~/.composer/cache',
    ]);

    if (file_exists($context['root_dir'] . '/infrastructure/docker/docker-compose.override.yml')) {
        $context['docker_compose_files'][] = 'docker-compose.override.yml';
    }

//    $composerDirectory = exec('composer global config cache-dir -q')->getOutput();
//
//    if ($composerDirectory) {
//        $context['composer_cache_dir'] = trim($composerDirectory);
//    }

    $platform = php_uname('s');

    if (stripos($platform, 'darwin') !== false) {
        $context['macos'] = true;
    }

    return $context;
}

function docker_compose(Context $context, string $command): \Symfony\Component\Process\Process
{
    $domains = [$context['root_domain'], ...$context['extra_domains']];
    $domains = implode(' ', $domains);

    $environment = [
        'PROJECT_NAME' => $context['project_name'],
        'PROJECT_DIRECTORY' => $context['project_directory'],
        'PROJECT_ROOT_DOMAIN' => $context['root_domain'],
        'PROJECT_DOMAINS' => $domains,
        'PROJECT_START_WORKERS' => $context['start_workers'],
        'COMPOSER_CACHE_DIR' => $context['composer_cache_dir'],
        'PHP_VERSION' => $context['php_version'],
    ];

    $dockerComposeFiles = implode(' -f ', array_map(function ($file) use ($context) {
        return $context['root_dir'] . '/infrastructure/docker/' . $file;
    }, $context['docker_compose_files']));

    $command = "docker compose -p {$context['project_name']} -f $dockerComposeFiles $command";

    return exec($command, environment: $environment);
}

#[Task(description: 'Build the infrastructure')]
function build(Context $context) {
    $command = "build --build-arg PROJECT_NAME={$context['project_name']} --build-arg USER_ID={$context['user_id']} --build-arg PHP_VERSION={$context['php_version']}";

    foreach ($context['services_to_build_first'] as $service) {
        $serviceCommand = "$command $service";

        docker_compose($context, $serviceCommand);
    }

    return docker_compose($context, $command);
}

#[Task(description: 'Build and start the infrastructure')]
function up(Context $context) {
    build($context);

    return docker_compose($context, 'up --remove-orphans --detach');
}
