<?php

/**
 * Bootstrap for running package tests via the host app's autoloader.
 * Usage from host app: phpunit --configuration=vendor/alidaaer/laravel-ai-agent/phpunit.xml
 */

// Try package's own vendor first, then walk up to find a host app
$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',           // Package standalone (composer install in package)
    __DIR__ . '/../../../autoload.php',             // Installed as a dependency (vendor/alidaaer/laravel-ai-agent/tests)
    getcwd() . '/vendor/autoload.php',             // Run from host app root (symlink case)
];

$loader = null;
foreach ($autoloaders as $autoloader) {
    if (file_exists($autoloader)) {
        $loader = require $autoloader;
        break;
    }
}

if (!$loader) {
    die('Cannot find autoload.php. Run composer install or run from your Laravel project root.' . PHP_EOL);
}

// Register the package test namespace
$loader->addPsr4('LaravelAIAgent\\Tests\\', __DIR__);
