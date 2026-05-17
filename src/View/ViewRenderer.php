<?php

declare(strict_types=1);

namespace Luna\View;

use RuntimeException;

final class ViewRenderer
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($this->resolve($template), $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile(
            $this->resolve($layout),
            array_merge($data, ['content' => $content]),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $file, array $data): string
    {
        if (! is_file($file)) {
            throw new RuntimeException(sprintf('View template "%s" does not exist.', $file));
        }

        ob_start();

        try {
            extract($data, EXTR_SKIP);
            require $file;
        } finally {
            $content = ob_get_clean();
        }

        return $content === false ? '' : $content;
    }

    private function resolve(string $template): string
    {
        $template = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template), DIRECTORY_SEPARATOR);

        if (! str_ends_with($template, '.php')) {
            $template .= '.php';
        }

        return rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template;
    }
}
