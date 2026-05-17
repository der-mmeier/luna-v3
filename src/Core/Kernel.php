<?php

declare(strict_types=1);

namespace Luna\Core;

final class Kernel
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function handle(): string
    {
        $config = $this->app->config();
        $appName = $config->string('APP_NAME', 'Luna V3');
        $environment = $config->string('APP_ENV', 'local');
        $debug = $config->bool('APP_DEBUG', false) ? 'enabled' : 'disabled';

        return sprintf(
            "%s\nEnvironment: %s\nDebug: %s\n",
            $appName,
            $environment,
            $debug,
        );
    }
}
