<?php

use Castor\Attribute\AsTask;

use function Castor\fs;

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
}
