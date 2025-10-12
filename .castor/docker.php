<?php

namespace docker;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Castor\Context;
use Castor\Helper\PathHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;

use function Castor\capture;
use function Castor\context;
use function Castor\finder;
use function Castor\fs;
use function Castor\http_client;
use function Castor\io;
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
            ->request('GET', \sprintf('http://%s:8080/api/http/routers', variable('root_domain')))
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
            if (!preg_match('{^Host\(`(?P<hosts>.*)`\)$}', $router['rule'], $matches)) {
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
    #[AsOption(description: 'The service to build (default: all services)', autocomplete: 'docker\get_service_names')]
    ?string $service = null,
    ?string $profile = null,
): void {
    generate_certificates(force: false);

    io()->title('Building infrastructure');

    $command = [];

    $command[] = '--profile';
    if ($profile) {
        $command[] = $profile;
    } else {
        $command[] = '*';
    }

    $command = [
        ...$command,
        'build',
        '--build-arg', 'PHP_VERSION=' . variable('php_version'),
        '--build-arg', 'PROJECT_NAME=' . variable('project_name'),
    ];

    if ($service) {
        $command[] = $service;
    }

    docker_compose($command);
}

/**
 * @param list<string> $profiles
 */
#[AsTask(description: 'Builds and starts the infrastructure', aliases: ['up'])]
function up(
    #[AsOption(description: 'The service to start (default: all services)', autocomplete: 'docker\get_service_names')]
    ?string $service = null,
    #[AsOption(mode: InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED)]
    array $profiles = [],
): void {
    if (!$service && !$profiles) {
        io()->title('Starting infrastructure');
    }

    $command = ['up', '--detach', '--wait', '--no-build'];

    if ($service) {
        $command[] = $service;
    }

    try {
        docker_compose($command, profiles: $profiles);
    } catch (ExceptionInterface $e) {
        io()->error('An error occurred while starting the infrastructure.');
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
    #[AsOption(description: 'The service to stop (default: all services)', autocomplete: 'docker\get_service_names')]
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
function ps(bool $ports = false): void
{
    $command = [
        'ps',
        '--format', 'table {{.Name}}\t{{.Image}}\t{{.Status}}\t{{.RunningFor}}\t{{.Command}}',
        '--no-trunc',
    ];

    if ($ports) {
        $command[2] .= '\t{{.Ports}}';
    }

    docker_compose($command, profiles: ['*']);

    if (!$ports) {
        io()->comment('You can use the "--ports" option to display ports.');
    }
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

    docker_compose(['down', '--remove-orphans', '--volumes', '--rmi=local'], profiles: ['*']);
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

    run(['infrastructure/docker/services/router/generate-ssl.sh'], context: context()->withQuiet());

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

    $command = ['up', '--detach', '--wait', '--no-build'];
    $profiles = ['worker', 'default'];

    try {
        docker_compose($command, profiles: $profiles);
    } catch (ProcessFailedException $e) {
        preg_match('/service "(\w+)" depends on undefined service "(\w+)"/', $e->getProcess()->getErrorOutput(), $matches);
        if (!$matches) {
            throw $e;
        }

        $r = new \ReflectionFunction(__FUNCTION__);

        io()->newLine();
        io()->error('An error occurred while starting the workers.');
        io()->warning(\sprintf(
            <<<'EOT'
                The "%1$s" service depends on the "%2$s" service, which is not defined in the current docker-compose configuration.

                Usually, this means that the service "%2$s" is not defined in the same profile (%3$s) as the "%1$s" service.

                You can try to add its profile in the current task: %4$s:%5$s
                EOT,
            $matches[1],
            $matches[2],
            implode(', ', $profiles),
            PathHelper::makeRelative((string) $r->getFileName()),
            $r->getStartLine(),
        ));
    }
}

#[AsTask(description: 'Stops the workers', namespace: 'docker:worker', name: 'stop', aliases: ['stop-workers'])]
function workers_stop(): void
{
    io()->title('Stopping workers');

    // Docker compose cannot stop a single service in a profile, if it depends
    // on another service in another profile. To make it work, we need to select
    // both profiles, and so stop both services

    // So we find all services, in all profiles, and manually filter the one
    // that has the "worker" profile, then we stop it
    $command = ['stop'];

    foreach (get_services() as $name => $service) {
        foreach ($service['profiles'] ?? [] as $profile) {
            if ('worker' === $profile) {
                $command[] = $name;

                continue 2;
            }
        }
    }

    docker_compose($command, profiles: ['*']);
}

/**
 * @param list<string> $subCommand
 * @param list<string> $profiles
 */
function docker_compose(array $subCommand, ?Context $c = null, array $profiles = []): Process
{
    $c ??= context();
    $profiles = $profiles ?: ['default'];

    $domains = [$c['root_domain'], ...$c['extra_domains']];
    $domains = '`' . implode('`) || Host(`', $domains) . '`';

    $c = $c->withEnvironment([
        'PROJECT_NAME' => $c['project_name'],
        'PROJECT_ROOT_DOMAIN' => $c['root_domain'],
        'PROJECT_DOMAINS' => $domains,
        'USER_ID' => $c['user_id'],
        'PHP_VERSION' => $c['php_version'],
        'REGISTRY' => $c['registry'] ?? '',
    ]);

    $command = [
        'docker',
        'compose',
        '-p', $c['project_name'],
    ];
    foreach ($profiles as $profile) {
        $command[] = '--profile';
        $command[] = $profile;
    }

    foreach ($c['docker_compose_files'] as $file) {
        $command[] = '-f';
        $command[] = $c['root_dir'] . '/infrastructure/docker/' . $file;
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
): Process {
    $c ??= context();

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

    foreach ($c['docker_compose_run_environment'] as $key => $value) {
        $command[] = '-e';
        $command[] = "{$key}={$value}";
    }

    $command[] = $service;
    $command[] = '/bin/bash';
    $command[] = '-c';
    $command[] = "{$runCommand}";

    return docker_compose($command, c: $c, profiles: ['*']);
}

function docker_exit_code(
    string $runCommand,
    ?Context $c = null,
    string $service = 'builder',
    bool $noDeps = true,
    ?string $workDir = null,
): int {
    $c = ($c ?? context())->withAllowFailure();

    $process = docker_compose_run(
        runCommand: $runCommand,
        c: $c,
        service: $service,
        noDeps: $noDeps,
        workDir: $workDir,
    );

    return $process->getExitCode() ?? 0;
}

// Mac users have a lot of problems running Yarn / Webpack on the Docker stack
// so this func allow them to run these tools on their host
function run_in_docker_or_locally_for_mac(string $command, ?Context $c = null): void
{
    $c ??= context();

    if ($c['macos']) {
        run($command, context: $c->withWorkingDirectory($c['root_dir']));
    } else {
        docker_compose_run($command, c: $c);
    }
}

#[AsTask(description: 'Push images cache to the registry', namespace: 'docker', name: 'push', aliases: ['push'])]
function push(bool $dryRun = false): void
{
    $registry = variable('registry');

    if (!$registry) {
        throw new \RuntimeException('You must define a registry to push images.');
    }

    // Generate bake file
    $targets = [];

    foreach (get_services() as $service => $config) {
        $cacheFrom = $config['build']['cache_from'][0] ?? null;

        if (null === $cacheFrom) {
            continue;
        }

        $cacheFrom = explode(',', $cacheFrom);
        $reference = null;
        $type = null;

        if (1 === \count($cacheFrom)) {
            $reference = $cacheFrom[0];
            $type = 'registry';
        } else {
            foreach ($cacheFrom as $part) {
                $from = explode('=', $part);

                if (2 !== \count($from)) {
                    continue;
                }

                if ('type' === $from[0]) {
                    $type = $from[1];
                }

                if ('ref' === $from[0]) {
                    $reference = $from[1];
                }
            }
        }

        $targets[] = [
            'reference' => $reference,
            'type' => $type,
            'context' => $config['build']['context'],
            'dockerfile' => $config['build']['dockerfile'] ?? 'Dockerfile',
            'target' => $config['build']['target'] ?? null,
        ];
    }

    $content = \sprintf(<<<'EOHCL'
        group "default" {
            targets = [%s]
        }

        EOHCL
        , implode(', ', array_map(fn ($target) => \sprintf('"%s"', $target['target']), $targets)));

    foreach ($targets as $target) {
        $content .= \sprintf(<<<'EOHCL'
            target "%s" {
                context    = "%s"
                dockerfile = "%s"
                cache-from = ["%s"]
                cache-to   = ["type=%s,ref=%s,mode=max"]
                target     = "%s"
                args = {
                    PHP_VERSION = "%s"
                }
            }

            EOHCL
            , $target['target'], $target['context'], $target['dockerfile'], $target['reference'], $target['type'], $target['reference'], $target['target'], variable('php_version'));
    }

    if ($dryRun) {
        io()->write($content);

        return;
    }

    // write bake file in tmp file
    $bakeFile = tempnam(sys_get_temp_dir(), 'bake');
    file_put_contents($bakeFile, $content);

    // Run bake
    run(['docker', 'buildx', 'bake', '-f', $bakeFile]);
}

/**
 * @return array<string, array{profiles?: list<string>, build: array{context: string, dockerfile?: string, cache_from?: list<string>, target?: string}}>
 */
function get_services(): array
{
    return json_decode(
        docker_compose(
            ['config', '--format', 'json'],
            context()->withQuiet(),
            profiles: ['*'],
        )->getOutput(),
        true,
    )['services'];
}

/**
 * @return string[]
 */
function get_service_names(): array
{
    return array_keys(get_services());
}
