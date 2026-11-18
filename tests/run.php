<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

/**
 * Jednoduchý vlastní testovací runner (bez závislosti na Composeru/PHPUnitu).
 *
 * Spuštění:
 *   php tests/run.php
 *
 * Každý testovací soubor (tests/*Test.php) registruje testy funkcí
 * test($name, $fn). Funkce $fn dostane jako argument čerstvé, izolované
 * PDO připojení k databázi v paměti (:memory:) se založeným schématem -
 * testy se tedy nijak neovlivňují a nezasahují do reálné databáze aplikace.
 */

require __DIR__ . '/../src/bootstrap.php';
// Načte autoloader a helpery, aby šly v testech používat třídy App\*.

final class AssertionFailedError extends \Exception
{
    // Vlastní výjimka, kterou vyhazují naše assert* funkce, když test
    // neprojde - odlišuje "test neuspěl" od "v kódu nastala jiná chyba".
}

function assertTrue(bool $condition, string $message = 'Očekávána hodnota true.'): void
{
    // Nejzákladnější kontrola - ověří, že podmínka je pravdivá.
    if (!$condition) {
        // Pokud podmínka NENÍ pravdivá.
        throw new AssertionFailedError($message);
        // Test selhal - vyhodí výjimku s chybovou hláškou.
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    // Ověří, že dvě hodnoty jsou shodné (včetně typu - operátor !== je
    // striktní porovnání, "5" a 5 by se považovaly za RŮZNÉ).
    if ($expected !== $actual) {
        // Pokud se hodnoty liší.
        if ($message === '') {
            // Pokud nebyla předaná vlastní chybová hláška.
            $message = sprintf(
                'Očekáváno %s, ale je %s.',
                var_export($expected, true),
                var_export($actual, true)
            );
            // Vygeneruje výchozí popisnou hlášku s oběma hodnotami
            // (var_export je zobrazí v čitelné/PHP syntaxi podobě).
        }
        throw new AssertionFailedError($message);
        // Test selhal.
    }
}

function assertThrows(string $exceptionClass, callable $fn, string $message = ''): void
{
    // Ověří, že zavolání $fn() vyhodí výjimku daného typu (např.
    // ValidationException) - používá se pro testování validací.
    try {
        $fn();
        // Zavolá testovanou funkci (anonymní funkci předanou v testu).
    } catch (\Throwable $e) {
        // Pokud $fn() vyhodila JAKOUKOLI výjimku/chybu.
        if ($e instanceof $exceptionClass) {
            // Pokud je to přesně (nebo podtřída) očekávané výjimky.
            return;
            // Vše v pořádku - test prošel, funkce končí.
        }
        throw new AssertionFailedError(
            // Vyhodila se jiná výjimka, než se čekalo - test selhal.
            $message !== '' ? $message : sprintf(
                'Očekávána výjimka %s, ale byla vyhozena %s: %s.',
                $exceptionClass,
                get_class($e),
                $e->getMessage()
            )
        );
    }
    throw new AssertionFailedError(
        // Pokud se $fn() dokončila BEZ vyhození jakékoli výjimky -
        // to je také chyba, protože jsme výjimku očekávali.
        $message !== '' ? $message : "Očekávaná výjimka {$exceptionClass} nebyla vyhozena."
    );
}

$GLOBALS['__tests'] = [];
// Globální pole, do kterého se postupně registrují všechny testy
// (dvojice [název testu, testovací funkce]).

/**
 * Zaregistruje jeden test. $fn(PDO $pdo): void
 */
function test(string $name, callable $fn): void
{
    // Funkce test() volaná v jednotlivých *Test.php souborech - jen
    // přidá test do seznamu, NEPROVÁDÍ ho hned (to se stane později,
    // v cyklu níž).
    $GLOBALS['__tests'][] = [$name, $fn];
}

foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    // glob() najde všechny soubory v tests/ odpovídající vzoru *Test.php
    // (CourseTest.php, ParticipantTest.php, EnrollmentTest.php...).
    require $testFile;
    // Načte (provede) daný soubor - tím se zavolají jeho test() volání
    // a testy se zaregistrují do $GLOBALS['__tests'].
}

echo "Spouštím automatické testy aplikace Kurzy a přihlašování účastníků...\n\n";
// Úvodní hláška na konzoli.

$passed = 0;
// Počítadlo úspěšných testů.
$failed = 0;
// Počítadlo neúspěšných testů.

foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    // Projde všechny zaregistrované testy.
    $pdo = \App\Database::createConnection(':memory:');
    // Pro KAŽDÝ test vytvoří úplně novou, prázdnou databázi v paměti
    // (žádný soubor na disku) - testy se tak nemůžou navzájem ovlivnit.
    try {
        $fn($pdo);
        // Zavolá samotnou testovací funkci s čerstvým připojením.
        echo "  [OK]   {$name}\n";
        // Test proběhl bez výjimky - úspěch.
        $passed++;
        // Zvýší počítadlo úspěšných testů.
    } catch (AssertionFailedError $e) {
        // Test neuspěl na některém assertTrue/assertSame/assertThrows.
        echo "  [FAIL] {$name}\n         -> {$e->getMessage()}\n";
        $failed++;
    } catch (\Throwable $e) {
        // Jakákoli jiná, neočekávaná chyba (např. chyba v samotném kódu
        // aplikace, ne v "assert" kontrole) - odlišeno jako [ERROR].
        echo "  [ERROR] {$name}\n         -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n" . str_repeat('-', 50) . "\n";
// Oddělovací čára (50 pomlček) pro přehlednost výstupu.
echo 'Celkem testů: ' . ($passed + $failed) . ", úspěšných: {$passed}, neúspěšných: {$failed}\n";
// Souhrnný řádek se statistikou.

exit($failed > 0 ? 1 : 0);
// Ukončí skript s návratovým kódem 0 (úspěch), pokud žádný test
// neselhal, jinak s kódem 1 (chyba) - užitečné pro automatizaci (CI).
