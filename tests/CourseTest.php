<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

use App\Repositories\CourseRepository;
// Import repository pro kurzy - to je přesně to, co testujeme.
use App\Validation\ValidationException;
// Import vlastní výjimky - testy ověřují, že se v chybových případech
// skutečně vyhazuje.

/**
 * POVINNÁ AUTOMATICKÁ KONTROLA ZE ZADÁNÍ:
 * "Na konci musí automatická kontrola vytvořit kurz a ověřit jeho uložení."
 */
test('Automatická kontrola: vytvoření kurzu a ověření jeho uložení', function (\PDO $pdo) {
    // Tohle je PŘESNĚ ten test, který zadání vyžaduje jako povinný -
    // vytvoří kurz a ověří, že se skutečně uložil do databáze.
    $repo = new CourseRepository($pdo);
    // Vytvoří repository nad čerstvou testovací databází (:memory:).

    $created = $repo->create([
        // Zavolá create() s platnými daty - mělo by proběhnout bez chyby.
        'name' => 'Úvod do PHP',
        'code' => 'PHP-101',
        'capacity' => 10,
        'starts_at' => '2026-12-01T09:00',
    ]);

    assertTrue($created->id !== null, 'Vytvořený kurz musí mít přidělené ID.');
    // Ověří, že nově vytvořený objekt Course má přidělené ID (= byl
    // skutečně vložen do databáze, jinak by ID zůstalo null).

    // Ověření uložení - kurz znovu dohledáme z databáze přes jeho ID.
    $found = $repo->find($created->id);
    // Nezávisle na vráceném objektu znovu načte kurz PŘÍMO Z DATABÁZE
    // podle ID - to je "ověření uložení" požadované zadáním.
    assertTrue($found !== null, 'Kurz musí být po vytvoření dohledatelný v databázi.');
    // Kurz musí v databázi skutečně existovat.
    assertSame('Úvod do PHP', $found['name']);
    // Uložený název musí odpovídat tomu, co jsme zadali.
    assertSame('PHP-101', $found['code']);
    // Uložený kód musí odpovídat.
    assertSame(10, $found['capacity']);
    // Uložená kapacita musí odpovídat (jako int, díky přetypování
    // v CourseRepository::find()).
    assertSame('2026-12-01T09:00', $found['starts_at']);
    // Uložený termín konání musí odpovídat.
});

test('Kód kurzu musí být jedinečný', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "Kód kurzu je jedinečný."
    $repo = new CourseRepository($pdo);
    $repo->create(['name' => 'Kurz A', 'code' => 'DUP', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    // Vytvoří první kurz s kódem "DUP".

    assertThrows(ValidationException::class, function () use ($repo) {
        // Očekává, že druhý pokus se STEJNÝM kódem vyhodí ValidationException.
        $repo->create(['name' => 'Kurz B', 'code' => 'DUP', 'capacity' => 3, 'starts_at' => '2026-02-01T10:00']);
    }, 'Druhý kurz se stejným kódem musí být odmítnut.');
});

test('Kapacita musí být celé číslo větší než 0', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "Kapacita je celé
    // číslo větší než 0." - ověřuje tři nesprávné případy.
    $repo = new CourseRepository($pdo);

    assertThrows(ValidationException::class, function () use ($repo) {
        // Kapacita 0 - není "větší než 0", musí být odmítnuta.
        $repo->create(['name' => 'Kurz', 'code' => 'CAP0', 'capacity' => 0, 'starts_at' => '2026-01-01T10:00']);
    }, 'Kapacita 0 musí být odmítnuta.');

    assertThrows(ValidationException::class, function () use ($repo) {
        // Záporná kapacita - musí být odmítnuta.
        $repo->create(['name' => 'Kurz', 'code' => 'CAPNEG', 'capacity' => -5, 'starts_at' => '2026-01-01T10:00']);
    }, 'Záporná kapacita musí být odmítnuta.');

    assertThrows(ValidationException::class, function () use ($repo) {
        // Nečíselná kapacita (text) - musí být odmítnuta.
        $repo->create(['name' => 'Kurz', 'code' => 'CAPSTR', 'capacity' => 'abc', 'starts_at' => '2026-01-01T10:00']);
    }, 'Nečíselná kapacita musí být odmítnuta.');
});

test('Úprava kurzu uloží změny a hlídá jedinečnost kódu', function (\PDO $pdo) {
    // Test funkce "úprava kurzu" (součást CRUD) a toho, že validace
    // jedinečnosti kódu funguje správně i při update (ne jen create).
    $repo = new CourseRepository($pdo);
    $c1 = $repo->create(['name' => 'Kurz 1', 'code' => 'C1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    // Vytvoří první kurz.
    $c2 = $repo->create(['name' => 'Kurz 2', 'code' => 'C2', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    // Vytvoří druhý kurz s jiným kódem.

    $repo->update($c1->id, ['name' => 'Kurz 1 upravený', 'code' => 'C1', 'capacity' => 7, 'starts_at' => '2026-03-01T10:00']);
    // Upraví první kurz (se STEJNÝM kódem "C1", jen jiným názvem/kapacitou/termínem)
    // - musí to projít, protože kolize se sebou samým se ignoruje.
    $found = $repo->find($c1->id);
    // Znovu načte kurz z databáze, aby ověřil, že se změny skutečně uložily.
    assertSame('Kurz 1 upravený', $found['name']);
    // Název musí být ten nový.
    assertSame(7, $found['capacity']);
    // Kapacita musí být ta nová.

    assertThrows(ValidationException::class, function () use ($repo, $c2) {
        // Pokus o přepsání kódu druhého kurzu na "C1" (kód prvního kurzu)
        // musí být odmítnut - kolize s JINÝM kurzem je chyba.
        $repo->update($c2->id, ['name' => 'Kurz 2', 'code' => 'C1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    }, 'Nelze změnit kód kurzu na kód, který už patří jinému kurzu.');
});

test('Smazání kurzu jej odstraní z databáze', function (\PDO $pdo) {
    // Test funkce "smazání kurzu" (součást CRUD).
    $repo = new CourseRepository($pdo);
    $c = $repo->create(['name' => 'Ke smazání', 'code' => 'DEL1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    // Vytvoří kurz, který hned smažeme.
    $repo->delete($c->id);
    // Zavolá smazání.
    assertTrue($repo->find($c->id) === null, 'Smazaný kurz nesmí být dohledatelný.');
    // Ověří, že smazaný kurz už v databázi není dohledatelný.
});

test('Filtr podle termínu a volných míst vrací jen odpovídající kurzy', function (\PDO $pdo) {
    // Test POŽADOVANÉ FUNKCE ZE ZADÁNÍ "Filtr kurzů podle termínu a volných míst."
    $repo = new CourseRepository($pdo);
    $repo->create(['name' => 'Brzký plný', 'code' => 'F1', 'capacity' => 1, 'starts_at' => '2026-01-10T09:00']);
    // Kurz konající se v lednu.
    $repo->create(['name' => 'Pozdní volný', 'code' => 'F2', 'capacity' => 5, 'starts_at' => '2026-06-10T09:00']);
    // Kurz konající se v červnu.

    $onlyJune = $repo->all(['from' => '2026-05-01', 'to' => '2026-07-01']);
    // Zfiltruje jen kurzy konající se mezi 1. 5. a 1. 7. 2026 - měl by
    // vyhovět jen červnový kurz.
    assertSame(1, count($onlyJune));
    // Měl by vrátit přesně jeden kurz.
    assertSame('F2', $onlyJune[0]['code']);
    // A to ten s kódem "F2" (ten pozdní).

    $all = $repo->all();
    // Bez filtru by měly vyjít oba kurzy.
    assertSame(2, count($all));
    // Ověří, že bez filtru skutečně vidíme oba.
});
