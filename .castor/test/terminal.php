<?php

// This file only tests how the commands run by docker-starter behave with and without a
// terminal (TTY, PTY, stdin). It is entirely self-contained: to drop it from a project
// generated from this template, just delete this file (and, if it was wired there, the
// corresponding step in .github/workflows/ci.yml).

namespace test;

use Castor\Attribute\AsTask;
use Symfony\Component\Console\Output\OutputInterface;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;
use function docker\docker_compose_exec;
use function docker\docker_compose_run;

// What a command gets: a terminal on stdin (to answer its prompts), or else the first line read
// on it, which must not wait forever; then whether it colors its output, as Symfony Console
// decides it in short (StreamOutput::hasColorSupport()): stdout is a terminal of a known type
const TERMINAL_PROBE = 'if [ -t 0 ]; then stdin=terminal; else stdin="read \"$(head -n 1)\""; fi; '
    . 'colors=no; [ -t 1 ] && case "$TERM" in screen*|xterm*|vt100*|vt220*|putty*|rxvt*|ansi*|cygwin*|linux*|*-256*) colors=yes; esac; '
    . 'echo "stdin: $stdin, colors: $colors"';

/**
 * Needs the stack to be started (for docker_compose_exec()), and the `script` command of
 * util-linux to emulate a terminal.
 */
#[AsTask(description: 'Tests how commands behave with and without a terminal')]
function terminal(): void
{
    io()->title('Testing how commands behave with and without a terminal');

    foreach (get_terminal_test_cases() as $section => $cases) {
        io()->section($section);

        foreach ($cases as $case) {
            $output = type_in_terminal($case->commandline, $case->typed);

            if ($case->expected !== $output) {
                throw new \RuntimeException(\sprintf('%s: expected "%s", got "%s".', $case->situation, $case->expected, $output));
            }

            io()->comment("{$case->situation}: OK");
        }
    }

    io()->success('Commands behave as expected with and without a terminal.');
}

/**
 * What "test:terminal" can not judge: how it looks, and how it feels to type in it.
 */
#[AsTask(description: 'Lists what to check by hand in a terminal (Linux)', namespace: 'test:terminal', name: 'manual')]
function terminal_manual(): void
{
    io()->title('Checking by hand how commands behave with and without a terminal');

    io()->text([
        'Run each command in your terminal, the stack being started and the QA tools installed,',
        'and check what follows it. Castor gives the command either your terminal as is (TTY),',
        'or a terminal of its own, which relays your terminal (PTY).',
    ]);

    $readline = <<<'SH'
        castor builder -- php -r 'echo "Hello ", readline("Name? "), "\n";'
        SH;

    $checks = [
        'In your terminal, given as is (TTY)' => [
            ['castor builder', 'A bash shell: "composer <Tab>" completes, the arrows and Ctrl+R go through the history, Ctrl+C stops a "sleep 30" but not the shell, Ctrl+D exits without any warning, even right after a Ctrl+C'],
            ['castor builder -- top', 'Full screen, at the size of the window, which it follows when resized; "q" quits'],
            [$readline, 'Asks your name, then greets you'],
            ['castor pg', 'A psql prompt, with its history; "\q" quits'],
        ],
        'In your terminal, relayed (PTY): the tasks running a command with docker_compose_run()' => [
            ['castor qa:cs --dry-run', 'Colors, and a progress bar redrawn in place'],
            ['castor qa:phpstan', 'Its "[OK] No errors" block is 80 columns wide, even in a wider window: the PTY has no size (a known limit)'],
            ['castor qa:phpstan', 'Ctrl+C stops it at once, and "docker ps" then lists no builder container'],
        ],
        'With pipes' => [
            ['castor qa:cs --dry-run | cat', 'Colors still: someone can answer the prompts'],
            ["echo Alice | {$readline}", 'Greets Alice, without asking'],
        ],
        'As in a CI: no terminal, not even a controlling one' => [
            ['CI=true setsid -w castor qa:phpstan < /dev/null 2>&1 | cat', 'Ends, without colors nor progress bar redrawn'],
            ["CI=true setsid -w {$readline} < /dev/null 2>&1 | cat", 'Does not wait for an answer: "Hello " right away'],
        ],
        'As an AI agent: the same, without CI (or ask yours to run them)' => [
            ['setsid -w castor qa:phpstan < /dev/null 2>&1 | cat', 'Ends, without colors nor progress bar redrawn'],
            ["setsid -w {$readline} < /dev/null 2>&1 | cat", 'Does not wait for an answer: "Hello " right away'],
        ],
    ];

    foreach ($checks as $section => $sectionChecks) {
        io()->section($section);

        foreach ($sectionChecks as [$commandline, $check]) {
            io()->writeln('$ ' . $commandline, OutputInterface::OUTPUT_RAW);
            io()->writeln('  ' . $check, OutputInterface::OUTPUT_RAW);
            io()->newLine();
        }
    }
}

#[AsTask(description: 'Runs the probe of "test:terminal"', namespace: 'test:terminal', name: 'probe')]
function terminal_probe(string $via): void
{
    $command = ['sh', '-c', TERMINAL_PROBE];

    match ($via) {
        'run' => run($command),
        'docker_compose_run' => docker_compose_run($command),
        'docker_compose_run_with_pty' => docker_compose_run($command, c: context()->withPty()),
        'docker_compose_exec' => docker_compose_exec($command, service: 'frontend'),
        default => throw new \InvalidArgumentException(\sprintf('Unknown way to run the probe: "%s".', $via)),
    };
}

/**
 * @return array<string, list<TerminalTestCase>> Indexed by section
 */
function get_terminal_test_cases(): array
{
    $terminal = 'stdin: terminal, colors: yes';
    $nothingRead = 'stdin: read "", colors: no';
    $helloRead = 'stdin: read "hello", colors: no';

    $cases = [];

    // Without a PTY, Castor gives its stdin to no command: only builder() forwards it (see below)
    foreach (['run', 'docker_compose_run', 'docker_compose_exec'] as $via) {
        $probe = "castor test:terminal:probe {$via}";

        $cases["{$via}()"] = [
            new TerminalTestCase('In a terminal', $probe, $terminal),
            // Someone can still answer the prompts (e.g. `castor init | tee init.log`)
            new TerminalTestCase('In a terminal, stdout piped', "{$probe} | cat", $terminal),
            // Nobody types in the terminal of a CI
            new TerminalTestCase('In a terminal, in a CI', "CI=true {$probe}", $nothingRead),
            new TerminalTestCase('Without a terminal (AI agent)', "{$probe} < /dev/null | cat", $nothingRead),
            new TerminalTestCase('Without a terminal, in a CI', "CI=true {$probe} < /dev/null | cat", $nothingRead),
            new TerminalTestCase('Without a terminal, stdin piped', "echo hello | {$probe} | cat", $nothingRead),
        ];
    }

    $cases['A PTY asked explicitly'] = [
        new TerminalTestCase('Without a terminal', 'castor test:terminal:probe docker_compose_run_with_pty < /dev/null | cat', $terminal),
    ];

    // It mirrors the terminal, the pipes and the redirections of Castor, as when the command is
    // run by hand
    $builder = 'castor builder -- sh -c ' . escapeshellarg(TERMINAL_PROBE);

    $cases['castor builder'] = [
        new TerminalTestCase('In a terminal', $builder, $terminal),
        // The terminal echoes what is typed
        new TerminalTestCase('In a terminal, stdout piped', "{$builder} | cat", "hello\n{$helloRead}", typed: "hello\n"),
        new TerminalTestCase('In a terminal, stdin piped', "echo hello | {$builder}", $helloRead),
        new TerminalTestCase('Without a terminal', "{$builder} < /dev/null | cat", $nothingRead),
        new TerminalTestCase('Without a terminal, stdin piped', "echo hello | {$builder} | cat", $helloRead),
    ];

    return $cases;
}

/**
 * Types the command line in a terminal emulated by script, and returns what it shows.
 */
function type_in_terminal(string $commandline, string $typed): string
{
    // Then nobody types anymore, but the terminal stays open: a command reading it waits, as
    // in a real one, instead of getting an end of file
    $input = (static function () use ($typed): \Generator {
        yield $typed;

        while (true) {
            yield '';
        }
    })();

    $output = capture(['script', '--quiet', '--return', '--command', $commandline, '/dev/null'], context: context()
        ->withInput($input)
        // A command waiting for an answer must fail the test, not block it
        ->withTimeout(60)
        ->withEnvironment([
            // The command lines set it themselves (it is set in GitHub Actions)
            'CI' => false,
            // As a terminal emulator does (it is not set in GitHub Actions)
            'TERM' => 'xterm-256color',
        ])
    );

    // The terminals end the lines with "\r\n" (even "\r\r\n", through two of them)
    return str_replace("\r", '', $output);
}
