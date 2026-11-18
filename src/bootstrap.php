<?php
// Otevírací PHP tag - od tohoto místa interpreter čte PHP kód.

declare(strict_types=1);
// Zapíná striktní typovou kontrolu - PHP už nebude "po tichu" převádět
// např. string na int, ale při nesedícím typu vyhodí chybu. Pomáhá to
// odhalit chyby dřív.

/**
 * Společný bootstrap pro web (public/index.php) i pro automatické testy
 * (tests/run.php). Registruje autoloader pro namespace App\ -> src/
 * a nastaví základní prostředí. Nepřipojuje se k databázi a nestartuje
 * session - to si řeší volající skript sám (web ano, testy ne).
 */

error_reporting(E_ALL);
// Zapne vypisování úplně všech typů chyb a upozornění (E_ALL) - užitečné
// při vývoji, abychom hned viděli i drobné chyby (notice/warning).

ini_set('display_errors', '1');
// Zajistí, že se chyby skutečně vypisují do výstupu (do prohlížeče/konzole),
// ne jen do log souboru.

date_default_timezone_set('Europe/Prague');
// Nastaví výchozí časovou zónu aplikace na českou, aby datumy a časy
// (např. enrolled_at) odpovídaly reálnému místnímu času.

spl_autoload_register(function (string $class): void {
    // Zaregistruje vlastní autoloader - funkci, kterou PHP automaticky
    // zavolá, když se použije třída, která ještě není nahraná (require).
    $prefix = 'App\\';
    // Namespace, který náš autoloader umí načítat - všechny naše třídy
    // žijí pod App\ (např. App\Models\Course).
    if (!str_starts_with($class, $prefix)) {
        // Pokud požadovaná třída nezačíná na "App\", nejde o naši třídu
        // (může to být vestavěná PHP třída jako PDO) - autoloader se
        // na ni nevztahuje.
        return;
        // Ukončí funkci bez dalšího kódu - necháme třídu neřešenou.
    }
    $relative = substr($class, strlen($prefix));
    // Odřeže prefix "App\" a zůstane např. "Models\Course".
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    // Namespace oddělovače "\" převede na lomítka "/" podle struktury
    // složek a slepí s touto složkou (src/) - vznikne reálná cesta
    // k souboru, např. src/Models/Course.php.
    if (is_file($path)) {
        // Ověří, že soubor na dané cestě skutečně existuje.
        require $path;
        // Načte (includne) daný PHP soubor, čímž se třída definuje.
    }
});

/**
 * Bezpečně escapuje hodnotu pro výstup do HTML.
 */
function h(mixed $value): string
{
    // Funkce h() (helper) - zkratka, aby se nemusel psát dlouhý
    // htmlspecialchars() pořád znova ve všech šablonách.
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    // Pokud je $value null, nahradí ho prázdným stringem; jinak ho
    // převede na string a escapuje speciální HTML znaky (<, >, &, uvozovky),
    // aby se zabránilo XSS útoku při vypisování uživatelského vstupu.
}

/**
 * Uloží flash zprávu do session (např. flash('success', 'Uloženo.')),
 * nebo ji přečte a smaže (flash('success')). Bezpečné volat i bez session.
 */
function flash(string $type, ?string $message = null): ?string
{
    // Flash zpráva = krátká zpráva, která "přežije" jen jeden další
    // požadavek (např. po redirectu zobrazí "Kurz byl vytvořen.").
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Pokud session není aktivní (např. v testech), nic neděláme.
        return null;
        // Vrátíme null, aby volající kód nespadl na chybě.
    }
    if ($message !== null) {
        // Pokud byla zpráva předána, jde o ZÁPIS (uložení zprávy).
        $_SESSION['flash'][$type] = $message;
        // Uloží zprávu do superglobální proměnné $_SESSION pod daným typem
        // (např. 'success' nebo 'error'), aby přežila redirect.

        return null;
        // Při zápisu nic nevracíme.
    }
    $value = $_SESSION['flash'][$type] ?? null;
    // Pokud zpráva nebyla předána, jde o ČTENÍ - přečteme uloženou hodnotu
    // (nebo null, pokud tam žádná není).
    unset($_SESSION['flash'][$type]);
    // Po přečtení zprávu ze session smažeme, aby se nezobrazila znovu
    // při dalším požadavku (proto "flash" - zablikne jen jednou).

    return $value;
    // Vrátí přečtenou hodnotu (nebo null).
}

/**
 * Přesměruje na danou cestu a skončí.
 */
function redirect(string $path): never
{
    // Pomocná funkce pro HTTP přesměrování (např. po uložení formuláře).
    header('Location: ' . $path);
    // Odešle HTTP hlavičku Location, která prohlížeči řekne, na jakou
    // adresu se má přesměrovat.
    exit;
    // Okamžitě zastaví běh skriptu - za redirectem už nemá smysl
    // pokračovat ve vykonávání kódu.
}
