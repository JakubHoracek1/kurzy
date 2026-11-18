<?php

declare(strict_types=1);

/**
 * Společný bootstrap pro web (public/index.php) i pro automatické testy
 * (tests/run.php). Registruje autoloader pro namespace App\ -> src/
 * a nastaví základní prostředí. Nepřipojuje se k databázi a nestartuje
 * session - to si řeší volající skript sám (web ano, testy ne).
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Europe/Prague');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

/**
 * Bezpečně escapuje hodnotu pro výstup do HTML.
 */
function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Uloží flash zprávu do session (např. flash('success', 'Uloženo.')),
 * nebo ji přečte a smaže (flash('success')). Bezpečné volat i bez session.
 */
function flash(string $type, ?string $message = null): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;

        return null;
    }
    $value = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);

    return $value;
}

/**
 * Přesměruje na danou cestu a skončí.
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}
