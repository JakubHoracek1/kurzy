<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Obaluje PDO/SQLite připojení a zajišťuje vytvoření schématu databáze
 * (database/schema.sql), pokud ještě neexistuje.
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Vrátí sdílené PDO připojení k souborové databázi aplikace.
     * Při prvním zavolání databázi vytvoří (CREATE TABLE IF NOT EXISTS ...).
     */
    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $path = dirname(__DIR__) . '/database/kurzy.sqlite';
            self::$instance = self::createConnection($path);
        }

        return self::$instance;
    }

    /**
     * Vytvoří nové, nezávislé připojení (typicky pro testy - např. ':memory:').
     */
    public static function createConnection(string $path): PDO
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);

        return $pdo;
    }

    /**
     * Spustí migrační SQL skript (idempotentní - CREATE TABLE/INDEX IF NOT EXISTS).
     */
    public static function migrate(PDO $pdo): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        if ($schema === false) {
            throw new \RuntimeException('Nepodařilo se načíst database/schema.sql');
        }
        $pdo->exec($schema);
    }

    /**
     * Zahodí sdílené připojení - využívá se jen v testech mezi jednotlivými běhy.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
