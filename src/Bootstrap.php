<?php

declare(strict_types=1);

namespace Luna;

use Dotenv\Dotenv;
use Luna\Config\Config;
use Luna\Core\Application;
use Luna\Core\Paths;

final class Bootstrap
{
    public static function init(string $rootPath): Application
    {
        $paths = new Paths($rootPath);

        if (is_file($paths->basePath('.env'))) {
            $dotenv = Dotenv::createImmutable($paths->basePath());
            $dotenv->load();
        }

        $config = new Config();

        return new Application($paths, $config);
    }
}
