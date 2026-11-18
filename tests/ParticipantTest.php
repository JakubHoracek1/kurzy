<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

use App\Repositories\ParticipantRepository;
// Import repository pro účastníky - testovaná třída.
use App\Validation\ValidationException;
// Import vlastní výjimky, kterou testy ověřují.

test('Vytvoření účastníka se uloží do databáze', function (\PDO $pdo) {
    // Obdoba povinné kontroly z CourseTest.php, jen pro účastníky.
    $repo = new ParticipantRepository($pdo);
    $created = $repo->create(['name' => 'Jana Nováková', 'email' => 'jana@example.com']);
    // Vytvoří účastníka s platnými daty.

    $found = $repo->find($created->id);
    // Znovu ho načte přímo z databáze podle ID.
    assertTrue($found !== null, 'Účastník musí být po vytvoření dohledatelný v databázi.');
    // Musí existovat.
    assertSame('Jana Nováková', $found['name']);
    // Jméno musí sedět.
    assertSame('jana@example.com', $found['email']);
    // E-mail musí sedět.
});

test('E-mail účastníka musí být platný', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "E-mail účastníka je platný."
    $repo = new ParticipantRepository($pdo);
    assertThrows(ValidationException::class, function () use ($repo) {
        // "neplatny-email" nemá formát e-mailu (chybí @ a domena) -
        // musí být odmítnut.
        $repo->create(['name' => 'Test', 'email' => 'neplatny-email']);
    }, 'Neplatný e-mail musí být odmítnut.');
});

test('E-mail účastníka musí být jedinečný', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "E-mail účastníka
    // je ... jedinečný."
    $repo = new ParticipantRepository($pdo);
    $repo->create(['name' => 'Petr', 'email' => 'petr@example.com']);
    // Vytvoří prvního účastníka s tímto e-mailem.

    assertThrows(ValidationException::class, function () use ($repo) {
        // Druhý pokus se STEJNÝM e-mailem musí být odmítnut.
        $repo->create(['name' => 'Petr Duplicitní', 'email' => 'petr@example.com']);
    }, 'Duplicitní e-mail musí být odmítnut.');
});

test('Úprava účastníka hlídá jedinečnost e-mailu', function (\PDO $pdo) {
    // Test funkce "úprava účastníka" (CRUD) + validace při update.
    $repo = new ParticipantRepository($pdo);
    $p1 = $repo->create(['name' => 'Účastník 1', 'email' => 'u1@example.com']);
    // Vytvoří prvního účastníka.
    $p2 = $repo->create(['name' => 'Účastník 2', 'email' => 'u2@example.com']);
    // Vytvoří druhého účastníka s jiným e-mailem.

    $repo->update($p1->id, ['name' => 'Účastník 1 upravený', 'email' => 'u1@example.com']);
    // Upraví prvního účastníka (se STEJNÝM e-mailem) - musí projít,
    // protože kolize se sebou samým se ignoruje.
    assertSame('Účastník 1 upravený', $repo->find($p1->id)['name']);
    // Ověří, že se jméno skutečně uložilo.

    assertThrows(ValidationException::class, function () use ($repo, $p2) {
        // Pokus o přepsání e-mailu druhého účastníka na e-mail prvního
        // musí být odmítnut.
        $repo->update($p2->id, ['name' => 'Účastník 2', 'email' => 'u1@example.com']);
    }, 'Nelze změnit e-mail na e-mail, který už patří jinému účastníkovi.');
});

test('Smazání účastníka jej odstraní z databáze', function (\PDO $pdo) {
    // Test funkce "smazání účastníka" (CRUD).
    $repo = new ParticipantRepository($pdo);
    $p = $repo->create(['name' => 'Ke smazání', 'email' => 'smazat@example.com']);
    // Vytvoří účastníka, kterého hned smažeme.
    $repo->delete($p->id);
    // Zavolá smazání.
    assertTrue($repo->find($p->id) === null, 'Smazaný účastník nesmí být dohledatelný.');
    // Ověří, že smazaný účastník už v databázi není dohledatelný.
});
