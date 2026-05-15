<?php

declare(strict_types=1);

namespace Luna;

use Dotenv\Dotenv;

final class Bootstrap
{
    public static function init(string $rootPath): void
    {
        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->safeLoad();

        $dotenv->required([
            'APP_NAME',
            'APP_ENV',
            'APP_DEBUG',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
        ]);
    }
}