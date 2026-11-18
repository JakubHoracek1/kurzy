<?php

declare(strict_types=1);

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

final class AssertionFailedError extends \Exception
{
}

function assertTrue(bool $condition, string $message = 'Očekávána hodnota true.'): void
{
    if (!$condition) {
        throw new AssertionFailedError($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        if ($message === '') {
            $message = sprintf(
                'Očekáváno %s, ale je %s.',
                var_export($expected, true),
                var_export($actual, true)
            );
        }
        throw new AssertionFailedError($message);
    }
}

function assertThrows(string $exceptionClass, callable $fn, string $message = ''): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new AssertionFailedError(
            $message !== '' ? $message : sprintf(
                'Očekávána výjimka %s, ale byla vyhozena %s: %s.',
                $exceptionClass,
                get_class($e),
                $e->getMessage()
            )
        );
    }
    throw new AssertionFailedError(
        $message !== '' ? $message : "Očekávaná výjimka {$exceptionClass} nebyla vyhozena."
    );
}

$GLOBALS['__tests'] = [];

/**
 * Zaregistruje jeden test. $fn(PDO $pdo): void
 */
function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

foreach (glob(__DIR__ . '/*Test.php') as $testFile) {
    require $testFile;
}

echo "Spouštím automatické testy aplikace Kurzy a přihlašování účastníků...\n\n";

$passed = 0;
$failed = 0;

foreach ($GLOBALS['__tests'] as [$name, $fn]) {
    $pdo = \App\Database::createConnection(':memory:');
    try {
        $fn($pdo);
        echo "  [OK]   {$name}\n";
        $passed++;
    } catch (AssertionFailedError $e) {
        echo "  [FAIL] {$name}\n         -> {$e->getMessage()}\n";
        $failed++;
    } catch (\Throwable $e) {
        echo "  [ERROR] {$name}\n         -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n" . str_repeat('-', 50) . "\n";
echo 'Celkem testů: ' . ($passed + $failed) . ", úspěšných: {$passed}, neúspěšných: {$failed}\n";

exit($failed > 0 ? 1 : 0);
