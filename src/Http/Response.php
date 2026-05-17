<?php

declare(strict_types=1);

namespace Luna\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $statusCode = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return new self(
            $json === false ? '{}' : $json,
            $statusCode,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function text(string $text, int $statusCode = 200): self
    {
        return new self($text, $statusCode, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function notFound(string $message = 'Not Found'): self
    {
        return self::text($message, 404);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->body, $this->statusCode, $headers);
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value));
            }
        }

        echo $this->body;
    }
}
