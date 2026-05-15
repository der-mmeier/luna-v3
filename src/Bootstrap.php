<?php

declare(strict_types=1);

namespace Luna;

use Dotenv\Dotenv;

final class Bootstrap
{
    public static function init(string $rootPath): void
    {
        if (! is_file($rootPath . '/.env')) {
            return;
        }

        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->load();
    }
}
