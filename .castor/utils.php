<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Process\Process;

use function Castor\cache;
use function Castor\capture;
use function Castor\get_context;
use function Castor\io;
use function Castor\log;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Displays some help and available urls for the current project')]
function about(): void
{
    io()->section('About this project');

    io()->comment('Run <comment>castor</comment> to display all available commands.');
    io()->comment('Run <comment>castor about</comment> to display this project help.');
    io()->comment('Run <comment>castor help [command]</comment> to display Castor help.');

    io()->section('Available URLs for this project:');
    $urls = [variable('root_domain'), ...variable('extra_domains')];

    $payload = @file_get_contents(sprintf('http://%s:8080/api/http/routers', variable('root_domain')));
    if ($payload) {
        $routers = json_decode($payload, true);
        $projectName = variable('project_name');
        foreach ($routers as $router) {
            if (!preg_match("{^{$projectName}-(.*)@docker$}", $router['name'])) {
                continue;
            }
            if ("frontend-{$projectName}" === $router['service']) {
                continue;
            }
            if (!preg_match('{^Host\\(`(?P<hosts>.*)`\\)$}', $router['rule'], $matches)) {
                continue;
            }
            $hosts = explode('`, `', $matches['hosts']);
            $urls = [...$urls, ...$hosts];
        }
    }
    io()->listing(array_map(fn ($url) => "https://{$url}", $urls));
}

#[AsTask(description: 'Opens a shell (bash) into a builder container')]
function builder(string $user = 'app'): void
{
    $c = get_context()
        ->withTimeout(null)
        ->withTty()
        ->withEnvironment($_ENV + $_SERVER)
        ->withQuiet()
        ->withAllowFailure()
    ;
    docker_compose_run('bash', c: $c, user: $user);
}

#[AsContext(default: true)]
function create_default_context(): Context
{
    $data = create_default_variables() + [
        'project_name' => 'app',
        'root_domain' => 'app.test',
        'extra_domains' => [],
        'project_directory' => 'application',
        'php_version' => '8.2',
        'docker_compose_files' => [
            'docker-compose.yml',
            'docker-compose.worker.yml',
        ],
        'macos' => false,
        'power_shell' => false,
        'user_id' => posix_geteuid(),
        'root_dir' => dirname(__DIR__),
        'env' => $_SERVER['CI'] ?? false ? 'ci' : 'dev',
    ];

    if (file_exists($data['root_dir'] . '/infrastructure/docker/docker-compose.override.yml')) {
        $data['docker_compose_files'][] = 'docker-compose.override.yml';
    }

    $data['composer_cache_dir'] = cache('composer_cache_dir', fn () => capture(['composer', 'global', 'config', 'cache-dir', '-q'], onFailure: sys_get_temp_dir() . '/castor/composer'));

    $platform = strtolower(php_uname('s'));
    if (str_contains($platform, 'darwin')) {
        $data['macos'] = true;
        $data['docker_compose_files'][] = 'docker-compose.docker-for-x.yml';
    } elseif (in_array($platform, ['win32', 'win64'])) {
        $data['docker_compose_files'][] = 'docker-compose.docker-for-x.yml';
        $data['power_shell'] = true;
    }

    if ($data['user_id'] > 256000) {
        $data['user_id'] = 1000;
    }

    if (0 === $data['user_id']) {
        log('Running as root? Fallback to fake user id.', 'warning');
        $data['user_id'] = 1000;
    }

    return new Context($data, pty: 'dev' === $data['env']);
}

function docker_compose_run(
    string $runCommand,
    Context $c = null,
    string $service = 'builder',
    string $user = 'app',
    bool $noDeps = true,
    string $workDir = null,
    bool $portMapping = false,
    bool $withBuilder = true,
): Process {
    $command = [
        'run',
        '--rm',
        '-u', $user,
    ];

    if ($noDeps) {
        $command[] = '--no-deps';
    }

    if ($portMapping) {
        $command[] = '--service-ports';
    }

    if (null !== $workDir) {
        $command[] = '-w';
        $command[] = $workDir;
    }

    $command[] = $service;
    $command[] = '/bin/sh';
    $command[] = '-c';
    $command[] = "exec {$runCommand}";

    return docker_compose($command, c: $c, withBuilder: $withBuilder);
}

/**
 * @param array<string> $subCommand
 */
function docker_compose(array $subCommand, Context $c = null, bool $withBuilder = false): Process
{
    $c ??= get_context();

    $domains = [variable('root_domain'), ...variable('extra_domains')];
    $domains = '`' . implode('`, `', $domains) . '`';

    $c = $c
        ->withTimeout(null)
        ->withEnvironment([
            'PROJECT_NAME' => variable('project_name'),
            'PROJECT_DIRECTORY' => variable('project_directory'),
            'PROJECT_ROOT_DOMAIN' => variable('root_domain'),
            'PROJECT_DOMAINS' => $domains,
            'COMPOSER_CACHE_DIR' => variable('composer_cache_dir'),
            'PHP_VERSION' => variable('php_version'),
        ], false)
    ;

    $command = [
        'docker',
        'compose',
        '-p', variable('project_name'),
    ];

    foreach (variable('docker_compose_files') as $file) {
        $command[] = '-f';
        $command[] = variable('root_dir') . '/infrastructure/docker/' . $file;
    }
    if ($withBuilder) {
        $command[] = '-f';
        $command[] = variable('root_dir') . '/infrastructure/docker/docker-compose.builder.yml';
    }

    $command = array_merge($command, $subCommand);

    return run($command, context: $c);
}

// Mac users have a lot of problems running Yarn / Webpack on the Docker stack
// so this func allow them to run these tools on their host
function run_in_docker_or_locally_for_mac(string $command, Context $c = null): void
{
    $c ??= get_context();

    if (variable('macos')) {
        run($command, context: $c->withPath(variable('root_dir')));
    } else {
        docker_compose_run($command, c: $c);
    }
}
