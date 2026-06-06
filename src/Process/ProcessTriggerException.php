<?php

declare(strict_types=1);

namespace Luna\Process;

use RuntimeException;

final class ProcessTriggerException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('Trigger wurde nicht gefunden.', 404);
    }

    public static function inactive(): self
    {
        return new self('Trigger ist inaktiv.', 409);
    }

    public static function forbidden(): self
    {
        return new self('Trigger Secret ist ungültig.', 403);
    }

    public static function notExecutable(string $message): self
    {
        return new self($message, 409);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
