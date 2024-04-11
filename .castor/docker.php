<?php

namespace docker;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function Castor\cache;
use function Castor\capture;
use function Castor\context;
use function Castor\finder;
use function Castor\fs;
use function Castor\http_client;
use function Castor\io;
use function Castor\log;
use function Castor\open;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Displays some help and available urls for the current project', namespace: '')]
function about(): void
{
    io()->title('About this project');

    io()->comment('Run <comment>castor</comment> to display all available commands.');
    io()->comment('Run <comment>castor about</comment> to display this project help.');
    io()->comment('Run <comment>castor help [command]</comment> to display Castor help.');

    io()->section('Available URLs for this project:');
    $urls = [variable('root_domain'), ...variable('extra_domains')];

    try {
        $routers = http_client()
            ->request('GET', sprintf('http://%s:8080/api/http/routers', variable('root_domain')))
            ->toArray()
        ;
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
            $hosts = explode('`) || Host(`', $matches['hosts']);
            $urls = [...$urls, ...$hosts];
        }
    } catch (HttpExceptionInterface) {
    }

    io()->listing(array_map(fn ($url) => "https://{$url}", array_unique($urls)));
}

#[AsTask(description: 'Opens the project in your browser', namespace: '', aliases: ['open'])]
function open_project(): void
{
    open('https://' . variable('root_domain'));
}

#[AsTask(description: 'Builds the infrastructure', aliases: ['build'])]
function build(
    ?string $service = null,
    ?string $profile = null,
): void {
    io()->title('Building infrastructure');

    $command = [];

    if ($profile) {
        $command[] = '--profile';
        $command[] = $profile;
    }

    $command = [
        ...$command,
        'build',
        '--build-arg', 'USER_ID=' . variable('user_id'),
        '--build-arg', 'PHP_VERSION=' . variable('php_version'),
        '--build-arg', 'PROJECT_NAME=' . variable('project_name'),
    ];

    if ($service) {
        $command[] = $service;
    }

    docker_compose($command, withBuilder: true);
}

/**
 * @param list<string> $profiles
 */
#[AsTask(description: 'Builds and starts the infrastructure', aliases: ['up'])]
function up(
    ?string $service = null,
    #[AsOption(mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED)]
    array $profiles = [],
): void {
    if (!$service && !$profiles) {
        io()->title('Starting infrastructure');
    }

    $command = ['up', '--detach', '--no-build'];

    if ($service) {
        $command[] = $service;
    }

    try {
        docker_compose($command, profiles: $profiles);
    } catch (ExceptionInterface $e) {
        io()->error('An error occured while starting the infrastructure.');
        io()->note('Did you forget to run "castor docker:build"?');
        io()->note('Or you forget to login to the registry?');

        throw $e;
    }
}

/**
 * @param list<string> $profiles
 */
#[AsTask(description: 'Stops the infrastructure', aliases: ['stop'])]
function stop(
    ?string $service = null,
    #[AsOption(mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED)]
    array $profiles = [],
): void {
    if (!$service || !$profiles) {
        io()->title('Stopping infrastructure');
    }

    $command = ['stop'];

    if ($service) {
        $command[] = $service;
    }

    docker_compose($command, profiles: $profiles);
}

#[AsTask(description: 'Opens a shell (bash) into a builder container', aliases: ['builder'])]
function builder(): void
{
    $c = context()
        ->withTimeout(null)
        ->withTty()
        ->withEnvironment($_ENV + $_SERVER)
        ->withAllowFailure()
    ;
    docker_compose_run('bash', c: $c);
}

/**
 * @param list<string> $profiles
 */
#[AsTask(description: 'Displays infrastructure logs', aliases: ['logs'])]
function logs(
    ?string $service = null,
    #[AsOption(mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED)]
    array $profiles = [],
): void {
    $command = ['logs', '-f', '--tail', '150'];

    if ($service) {
        $command[] = $service;
    }

    docker_compose($command, c: context()->withTty(), profiles: $profiles);
}

#[AsTask(description: 'Lists containers status', aliases: ['ps'])]
function ps(): void
{
    docker_compose(['ps'], withBuilder: false);
}

#[AsTask(description: 'Cleans the infrastructure (remove container, volume, networks)', aliases: ['destroy'])]
function destroy(
    #[AsOption(description: 'Force the destruction without confirmation', shortcut: 'f')]
    bool $force = false,
): void {
    io()->title('Destroying infrastructure');

    if (!$force) {
        io()->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        io()->note('You can use the --force option to avoid this confirmation.');
        if (!io()->confirm('Are you sure?', false)) {
            io()->comment('Aborted.');

            return;
        }
    }

    docker_compose(['down', '--remove-orphans', '--volumes', '--rmi=local'], withBuilder: true);
    $files = finder()
        ->in(variable('root_dir') . '/infrastructure/docker/services/router/certs/')
        ->name('*.pem')
        ->files()
    ;
    fs()->remove($files);
}

#[AsTask(description: 'Generates SSL certificates (with mkcert if available or self-signed if not)')]
function generate_certificates(
    #[AsOption(description: 'Force the certificates re-generation without confirmation', shortcut: 'f')]
    bool $force = false,
): void {
    $sslDir = variable('root_dir') . '/infrastructure/docker/services/router/certs';

    if (file_exists("{$sslDir}/cert.pem") && !$force) {
        io()->comment('SSL certificates already exists.');
        io()->note('Run "castor docker:generate-certificates --force" to generate new certificates.');

        return;
    }

    io()->title('Generating SSL certificates');

    if ($force) {
        if (file_exists($f = "{$sslDir}/cert.pem")) {
            io()->comment('Removing existing certificates in infrastructure/docker/services/router/certs/*.pem.');
            unlink($f);
        }

        if (file_exists($f = "{$sslDir}/key.pem")) {
            unlink($f);
        }
    }

    $finder = new ExecutableFinder();
    $mkcert = $finder->find('mkcert');

    if ($mkcert) {
        $pathCaRoot = capture(['mkcert', '-CAROOT']);

        if (!is_dir($pathCaRoot)) {
            io()->warning('You must have mkcert CA Root installed on your host with "mkcert -install" command.');

            return;
        }

        $rootDomain = variable('root_domain');

        run([
            'mkcert',
            '-cert-file', "{$sslDir}/cert.pem",
            '-key-file', "{$sslDir}/key.pem",
            $rootDomain,
            "*.{$rootDomain}",
            ...variable('extra_domains'),
        ]);

        io()->success('Successfully generated SSL certificates with mkcert.');

        if ($force) {
            io()->note('Please restart the infrastructure to use the new certificates with "castor up" or "castor start".');
        }

        return;
    }

    run(['infrastructure/docker/services/router/generate-ssl.sh'], quiet: true);

    io()->success('Successfully generated self-signed SSL certificates in infrastructure/docker/services/router/certs/*.pem.');
    io()->comment('Consider installing mkcert to generate locally trusted SSL certificates and run "castor docker:generate-certificates --force".');

    if ($force) {
        io()->note('Please restart the infrastructure to use the new certificates with "castor up" or "castor start".');
    }
}

#[AsTask(description: 'Starts the workers', namespace: 'docker:worker', name: 'start', aliases: ['start-workers'])]
function workers_start(): void
{
    io()->title('Starting workers');

    up(profiles: ['worker']);
}

#[AsTask(description: 'Stops the workers', namespace: 'docker:worker', name: 'stop', aliases: ['stop-workers'])]
function workers_stop(): void
{
    io()->title('Stopping workers');

    stop(profiles: ['worker']);
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
        'root_dir' => \dirname(__DIR__),
    ];

    if (file_exists($data['root_dir'] . '/infrastructure/docker/docker-compose.override.yml')) {
        $data['docker_compose_files'][] = 'docker-compose.override.yml';
    }

    // We need an empty context to run command, since the default context has
    // not been set in castor, since we ARE creating it right now
    $emptyContext = new Context();

    $data['composer_cache_dir'] = cache('composer_cache_dir', function () use ($emptyContext): string {
        $composerCacheDir = capture(['composer', 'global', 'config', 'cache-dir', '-q'], onFailure: '', context: $emptyContext);
        // If PHP is broken, the output will not be a valid path but an error message
        if (!is_dir($composerCacheDir)) {
            $composerCacheDir = sys_get_temp_dir() . '/castor/composer';
            // If the directory does not exist, we create it. Otherwise, docker
            // will do, as root, and the user will not be able to write in it.
            if (!is_dir($composerCacheDir)) {
                mkdir($composerCacheDir, 0o777, true);
            }
        }

        return $composerCacheDir;
    });

    $platform = strtolower(php_uname('s'));
    if (str_contains($platform, 'darwin')) {
        $data['macos'] = true;
        $data['docker_compose_files'][] = 'docker-compose.docker-for-x.yml';
    } elseif (\in_array($platform, ['win32', 'win64'])) {
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

    return new Context(
        $data,
        pty: Process::isPtySupported(),
        environment: [
            'BUILDKIT_PROGRESS' => 'plain',
        ]
    );
}

#[AsContext(name: 'ci')]
function create_ci_context(): Context
{
    $c = create_default_context();

    return $c
        ->withData([
            // override the default context here
        ])
        ->withEnvironment([
            'COMPOSE_ANSI' => 'never',
        ])
    ;
}

/**
 * @param list<string> $subCommand
 * @param list<string> $profiles
 */
function docker_compose(array $subCommand, ?Context $c = null, bool $withBuilder = false, array $profiles = []): Process
{
    $c ??= context();
    $profiles = $profiles ?: ['default'];

    $domains = [variable('root_domain'), ...variable('extra_domains')];
    $domains = '`' . implode('`) || Host(`', $domains) . '`';

    $c = $c
        ->withTimeout(null)
        ->withEnvironment([
            'PROJECT_NAME' => variable('project_name'),
            'PROJECT_ROOT_DOMAIN' => variable('root_domain'),
            'PROJECT_DOMAINS' => $domains,
            'USER_ID' => variable('user_id'),
            'COMPOSER_CACHE_DIR' => variable('composer_cache_dir'),
            'PHP_VERSION' => variable('php_version'),
        ])
    ;

    $command = [
        'docker',
        'compose',
        '-p', variable('project_name'),
    ];
    foreach ($profiles as $profile) {
        $command[] = '--profile';
        $command[] = $profile;
    }

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

function docker_compose_run(
    string $runCommand,
    ?Context $c = null,
    string $service = 'builder',
    bool $noDeps = true,
    ?string $workDir = null,
    bool $portMapping = false,
    bool $withBuilder = true,
): Process {
    $command = [
        'run',
        '--rm',
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

function docker_exit_code(
    string $runCommand,
    ?Context $c = null,
    string $service = 'builder',
    bool $noDeps = true,
    ?string $workDir = null,
    bool $withBuilder = true,
): int {
    $c = ($c ?? context())->withAllowFailure();

    $process = docker_compose_run(
        runCommand: $runCommand,
        c: $c,
        service: $service,
        noDeps: $noDeps,
        workDir: $workDir,
        withBuilder: $withBuilder,
    );

    return $process->getExitCode() ?? 0;
}

// Mac users have a lot of problems running Yarn / Webpack on the Docker stack
// so this func allow them to run these tools on their host
function run_in_docker_or_locally_for_mac(string $command, ?Context $c = null): void
{
    $c ??= context();

    if (variable('macos')) {
        run($command, context: $c->withPath(variable('root_dir')));
    } else {
        docker_compose_run($command, c: $c);
    }
}
