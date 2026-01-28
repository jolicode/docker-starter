<?php

echo 'Hello world from PHP ', \PHP_MAJOR_VERSION, '.', \PHP_MINOR_VERSION, '.', \PHP_RELEASE_VERSION, " inside Docker! \n";

echo 'Environment: ', $_SERVER['APP_ENV'] ?? 'not set', "\n";
