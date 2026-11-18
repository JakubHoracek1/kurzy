<?php

declare(strict_types=1);

use App\Repositories\CourseRepository;
use App\Validation\ValidationException;

/**
 * POVINNÁ AUTOMATICKÁ KONTROLA ZE ZADÁNÍ:
 * "Na konci musí automatická kontrola vytvořit kurz a ověřit jeho uložení."
 */
test('Automatická kontrola: vytvoření kurzu a ověření jeho uložení', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);

    $created = $repo->create([
        'name' => 'Úvod do PHP',
        'code' => 'PHP-101',
        'capacity' => 10,
        'starts_at' => '2026-12-01T09:00',
    ]);

    assertTrue($created->id !== null, 'Vytvořený kurz musí mít přidělené ID.');

    // Ověření uložení - kurz znovu dohledáme z databáze přes jeho ID.
    $found = $repo->find($created->id);
    assertTrue($found !== null, 'Kurz musí být po vytvoření dohledatelný v databázi.');
    assertSame('Úvod do PHP', $found['name']);
    assertSame('PHP-101', $found['code']);
    assertSame(10, $found['capacity']);
    assertSame('2026-12-01T09:00', $found['starts_at']);
});

test('Kód kurzu musí být jedinečný', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);
    $repo->create(['name' => 'Kurz A', 'code' => 'DUP', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);

    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Kurz B', 'code' => 'DUP', 'capacity' => 3, 'starts_at' => '2026-02-01T10:00']);
    }, 'Druhý kurz se stejným kódem musí být odmítnut.');
});

test('Kapacita musí být celé číslo větší než 0', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);

    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Kurz', 'code' => 'CAP0', 'capacity' => 0, 'starts_at' => '2026-01-01T10:00']);
    }, 'Kapacita 0 musí být odmítnuta.');

    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Kurz', 'code' => 'CAPNEG', 'capacity' => -5, 'starts_at' => '2026-01-01T10:00']);
    }, 'Záporná kapacita musí být odmítnuta.');

    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Kurz', 'code' => 'CAPSTR', 'capacity' => 'abc', 'starts_at' => '2026-01-01T10:00']);
    }, 'Nečíselná kapacita musí být odmítnuta.');
});

test('Úprava kurzu uloží změny a hlídá jedinečnost kódu', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);
    $c1 = $repo->create(['name' => 'Kurz 1', 'code' => 'C1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    $c2 = $repo->create(['name' => 'Kurz 2', 'code' => 'C2', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);

    $repo->update($c1->id, ['name' => 'Kurz 1 upravený', 'code' => 'C1', 'capacity' => 7, 'starts_at' => '2026-03-01T10:00']);
    $found = $repo->find($c1->id);
    assertSame('Kurz 1 upravený', $found['name']);
    assertSame(7, $found['capacity']);

    assertThrows(ValidationException::class, function () use ($repo, $c2) {
        $repo->update($c2->id, ['name' => 'Kurz 2', 'code' => 'C1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    }, 'Nelze změnit kód kurzu na kód, který už patří jinému kurzu.');
});

test('Smazání kurzu jej odstraní z databáze', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);
    $c = $repo->create(['name' => 'Ke smazání', 'code' => 'DEL1', 'capacity' => 5, 'starts_at' => '2026-01-01T10:00']);
    $repo->delete($c->id);
    assertTrue($repo->find($c->id) === null, 'Smazaný kurz nesmí být dohledatelný.');
});

test('Filtr podle termínu a volných míst vrací jen odpovídající kurzy', function (\PDO $pdo) {
    $repo = new CourseRepository($pdo);
    $repo->create(['name' => 'Brzký plný', 'code' => 'F1', 'capacity' => 1, 'starts_at' => '2026-01-10T09:00']);
    $repo->create(['name' => 'Pozdní volný', 'code' => 'F2', 'capacity' => 5, 'starts_at' => '2026-06-10T09:00']);

    $onlyJune = $repo->all(['from' => '2026-05-01', 'to' => '2026-07-01']);
    assertSame(1, count($onlyJune));
    assertSame('F2', $onlyJune[0]['code']);

    $all = $repo->all();
    assertSame(2, count($all));
});
