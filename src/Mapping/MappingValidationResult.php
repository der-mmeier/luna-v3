<?php

declare(strict_types=1);

namespace Luna\Mapping;

final class MappingValidationResult
{
    private array $errors = [];

    private array $warnings = [];

    private array $infos = [];

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addInfo(string $message): void
    {
        $this->infos[] = $message;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function infos(): array
    {
        return $this->infos;
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
