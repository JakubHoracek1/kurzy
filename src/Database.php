<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola - viz vysvětlení v bootstrap.php.

namespace App;
// Tato třída patří do namespace App - díky tomu ji autoloader najde
// na cestě src/Database.php (App\Database -> src/Database.php).

use PDO;
// Zkrátí psaní \PDO na PDO - PDO je vestavěná PHP třída pro přístup
// k databázím (zde SQLite) jedním společným rozhraním.

/**
 * Obaluje PDO/SQLite připojení a zajišťuje vytvoření schématu databáze
 * (database/schema.sql), pokud ještě neexistuje.
 */
final class Database
{
    // "final" znamená, že se z této třídy nedá dědit (nemá to tu smysl,
    // je to jen statický obal nad připojením).

    private static ?PDO $instance = null;
    // Statická (společná pro celou aplikaci) proměnná, kde se po prvním
    // přihlášení uloží jedno PDO připojení (tzv. Singleton vzor) - aby se
    // databáze neotevírala pořád znovu.

    /**
     * Vrátí sdílené PDO připojení k souborové databázi aplikace.
     * Při prvním zavolání databázi vytvoří (CREATE TABLE IF NOT EXISTS ...).
     */
    public static function connection(): PDO
    {
        // Statická metoda - voláme ji jako Database::connection(), bez
        // vytváření instance třídy (new Database() se nepoužívá).
        if (self::$instance === null) {
            // Pokud ještě žádné připojení neexistuje (první volání).
            $path = dirname(__DIR__) . '/database/kurzy.sqlite';
            // Sestaví absolutní cestu k souboru databáze: dirname(__DIR__)
            // je kořen projektu (o úroveň nad src/), + database/kurzy.sqlite.
            self::$instance = self::createConnection($path);
            // Vytvoří nové připojení a uloží ho do statické proměnné,
            // aby se příště už jen vracelo (cache).
        }

        return self::$instance;
        // Vrátí už existující (nebo právě vytvořené) připojení.
    }

    /**
     * Vytvoří nové, nezávislé připojení (typicky pro testy - např. ':memory:').
     */
    public static function createConnection(string $path): PDO
    {
        // Na rozdíl od connection() tahle metoda VŽDY vytvoří nové
        // připojení - používá se v testech, kde chceme čerstvou,
        // izolovanou databázi pro každý test.
        $pdo = new PDO('sqlite:' . $path);
        // Vytvoří PDO připojení k SQLite databázi na dané cestě
        // (nebo ':memory:' pro databázi jen v paměti RAM, bez souboru).
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Nastaví, že při chybě v SQL dotazu PDO vyhodí výjimku (Exception),
        // místo aby chybu jen tiše ignorovalo nebo vracelo false.
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Nastaví, že výsledky dotazů se budou standardně vracet jako
        // asociativní pole (např. ['id' => 1, 'name' => 'Kurz']), ne jako
        // objekty nebo číselně indexovaná pole.
        $pdo->exec('PRAGMA foreign_keys = ON');
        // SQLite má cizí klíče (FOREIGN KEY) defaultně VYPNUTÉ - tímto
        // příkazem je pro toto připojení zapneme, aby fungovalo
        // ON DELETE CASCADE (smazání kurzu smaže i jeho přihlášky).
        self::migrate($pdo);
        // Zajistí, že v databázi existují všechny potřebné tabulky.

        return $pdo;
        // Vrátí hotové, nakonfigurované připojení.
    }

    /**
     * Spustí migrační SQL skript (idempotentní - CREATE TABLE/INDEX IF NOT EXISTS).
     */
    public static function migrate(PDO $pdo): void
    {
        // "Migrace" = vytvoření/aktualizace struktury databáze podle
        // schema.sql. Díky "IF NOT EXISTS" je bezpečné ji spustit
        // opakovaně (nic se nerozbije, pokud tabulky už existují).
        $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        // Přečte celý obsah souboru database/schema.sql jako text.
        if ($schema === false) {
            // file_get_contents() vrátí false, pokud se soubor nepodařilo
            // načíst (např. neexistuje).
            throw new \RuntimeException('Nepodařilo se načíst database/schema.sql');
            // Vyhodí výjimku - bez schématu nemá smysl pokračovat.
        }
        $pdo->exec($schema);
        // Spustí celý obsah souboru jako SQL příkazy (CREATE TABLE, INDEX...).
    }

    /**
     * Zahodí sdílené připojení - využívá se jen v testech mezi jednotlivými běhy.
     */
    public static function reset(): void
    {
        self::$instance = null;
        // Vynuluje statickou proměnnou, takže příští connection() si
        // vytvoří úplně nové připojení.
    }
}
