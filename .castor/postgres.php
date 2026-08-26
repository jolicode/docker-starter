<?php

namespace postgres;

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function docker\docker_compose;
use function docker\docker_compose_exec;

/**
 * Same idea as "castor builder -- <command>": proxies anything after "--" to
 * psql inside the postgres container, in a non-interactive way with the exit
 * code propagated.
 *
 * castor pg                          # interactive psql
 * castor pg -- "SELECT now();"       # one-shot query
 * castor pg -- -c "\dt"              # raw psql arguments
 *
 * @param list<string> $params
 */
#[AsTask(description: 'Connect to the PostgreSQL database', aliases: ['postgres', 'pg'])]
function client(#[AsArgsAfterOptionEnd] array $params = []): int
{
    $command = ['psql', '-U', 'app', 'app'];

    if (!$params) {
        io()->title('Connecting to the PostgreSQL database');

        docker_compose(['exec', 'postgres', ...$command], context()->toInteractive());

        return 0;
    }

    // Rebuild the query as a single psql argument, as the shell splits it
    // into multiple words ("castor pg -- SELECT 1;" gives ['SELECT', '1;'])
    if (!str_starts_with((string) reset($params), '-')) {
        $params = ['-c', implode(' ', $params)];
    }

    return (int) docker_compose_exec([...$command, ...$params], service: 'postgres')->getExitCode();
}
