<?php

namespace test;

/**
 * A case of "test:terminal" (see terminal.php).
 */
final readonly class TerminalTestCase
{
    public function __construct(
        public string $situation,
        // As typed in a terminal
        public string $commandline,
        // What the terminal then shows
        public string $expected,
        // What is typed while the command runs
        public string $typed = '',
    ) {
    }
}
