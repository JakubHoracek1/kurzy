<?php

declare(strict_types=1);

namespace App;

/**
 * Jednoduchý renderer šablon: vykreslí view soubor do bufferu
 * a vloží jej do společného layoutu (views/layout.php).
 */
final class View
{
    public static function render(string $template, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        extract($data, EXTR_SKIP);

        $viewFile = dirname(__DIR__) . '/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("Šablona '{$template}' nenalezena.");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require dirname(__DIR__) . '/views/layout.php';
    }
}
