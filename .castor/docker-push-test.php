<?php

// This file only tests the "castor docker:push" task, against a real, throwaway
// registry started and destroyed in Docker. It is entirely self-contained: to
// drop it from a project generated from this template, just delete this file
// (and, if it was wired there, the corresponding step in .github/workflows/ci.yml).

namespace docker;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\http_client;
use function Castor\io;
use function Castor\run;
use function Castor\wait_for_http_status;

#[AsTask(description: 'Tests "docker:push" against a throwaway local registry', namespace: 'docker', name: 'test-push')]
function test_push(): void
{
    io()->title('Testing docker:push against a throwaway registry');

    $services = array_keys(array_filter(
        get_services(),
        static fn (array $config) => isset($config['build']['cache_from']),
    ));

    if (!$services) {
        throw new \RuntimeException('No service defines a "cache_from", there is nothing to test.');
    }

    // A dedicated network is required so that the buildx builder (which, on CI runners,
    // builds inside its own container) can reach the registry by its container name,
    // regardless of which buildx driver is used.
    $suffix = bin2hex(random_bytes(4));
    $network = "docker-starter-test-{$suffix}";
    $registryName = "docker-starter-test-registry-{$suffix}";
    $builderName = "docker-starter-test-builder-{$suffix}";
    $namespace = 'docker-starter-test';

    io()->section('Starting a throwaway registry');
    run(['docker', 'network', 'create', $network]);
    run([
        'docker', 'run', '--detach', '--rm',
        '--name', $registryName,
        '--network', $network,
        '--publish', '127.0.0.1::5000',
        'registry:2',
    ]);

    try {
        [$host, $port] = explode(':', trim(capture(['docker', 'port', $registryName, '5000/tcp'])));
        $hostRegistry = "{$host}:{$port}";

        wait_for_http_status("http://{$hostRegistry}/v2/", message: 'Waiting for the throwaway registry to be available...');

        io()->section('Creating a throwaway buildx builder on the same network');

        // The builder runs in its own container and, unlike the Docker daemon, does not
        // trust plain HTTP registries by default: it must be told explicitly.
        $buildkitConfig = tempnam(sys_get_temp_dir(), 'buildkitd-');
        file_put_contents($buildkitConfig, <<<TOML
            [registry."{$registryName}:5000"]
              http = true
            TOML);

        try {
            run(['docker', 'buildx', 'create', '--name', $builderName, '--driver', 'docker-container', '--driver-opt', "network={$network}", '--config', $buildkitConfig]);

            io()->section('Running docker:push against it');
            run(['castor', 'docker:push'], context: context()->withEnvironment([
                'DS_REGISTRY' => "{$registryName}:5000/{$namespace}",
                'BUILDX_BUILDER' => $builderName,
            ]));
        } finally {
            run(['docker', 'buildx', 'rm', $builderName], context: context()->withAllowFailure()->withQuiet());
            unlink($buildkitConfig);
        }

        io()->section('Checking the pushed images');
        foreach ($services as $service) {
            $tags = http_client()
                ->request('GET', "http://{$hostRegistry}/v2/{$namespace}/{$service}/tags/list")
                ->toArray()
            ;

            if (!\in_array('cache', $tags['tags'] ?? [], true)) {
                throw new \RuntimeException(\sprintf('Expected a "cache" tag for "%s" in the registry, got: %s', $service, json_encode($tags)));
            }

            io()->comment("{$service}: OK");
        }
    } finally {
        io()->section('Removing the throwaway registry');
        run(['docker', 'stop', $registryName], context: context()->withAllowFailure()->withQuiet());
        run(['docker', 'network', 'rm', $network], context: context()->withAllowFailure()->withQuiet());
    }

    io()->success('docker:push correctly pushed the cache images to the registry.');
}
