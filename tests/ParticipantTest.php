<?php

declare(strict_types=1);

use App\Repositories\ParticipantRepository;
use App\Validation\ValidationException;

test('Vytvoření účastníka se uloží do databáze', function (\PDO $pdo) {
    $repo = new ParticipantRepository($pdo);
    $created = $repo->create(['name' => 'Jana Nováková', 'email' => 'jana@example.com']);

    $found = $repo->find($created->id);
    assertTrue($found !== null, 'Účastník musí být po vytvoření dohledatelný v databázi.');
    assertSame('Jana Nováková', $found['name']);
    assertSame('jana@example.com', $found['email']);
});

test('E-mail účastníka musí být platný', function (\PDO $pdo) {
    $repo = new ParticipantRepository($pdo);
    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Test', 'email' => 'neplatny-email']);
    }, 'Neplatný e-mail musí být odmítnut.');
});

test('E-mail účastníka musí být jedinečný', function (\PDO $pdo) {
    $repo = new ParticipantRepository($pdo);
    $repo->create(['name' => 'Petr', 'email' => 'petr@example.com']);

    assertThrows(ValidationException::class, function () use ($repo) {
        $repo->create(['name' => 'Petr Duplicitní', 'email' => 'petr@example.com']);
    }, 'Duplicitní e-mail musí být odmítnut.');
});

test('Úprava účastníka hlídá jedinečnost e-mailu', function (\PDO $pdo) {
    $repo = new ParticipantRepository($pdo);
    $p1 = $repo->create(['name' => 'Účastník 1', 'email' => 'u1@example.com']);
    $p2 = $repo->create(['name' => 'Účastník 2', 'email' => 'u2@example.com']);

    $repo->update($p1->id, ['name' => 'Účastník 1 upravený', 'email' => 'u1@example.com']);
    assertSame('Účastník 1 upravený', $repo->find($p1->id)['name']);

    assertThrows(ValidationException::class, function () use ($repo, $p2) {
        $repo->update($p2->id, ['name' => 'Účastník 2', 'email' => 'u1@example.com']);
    }, 'Nelze změnit e-mail na e-mail, který už patří jinému účastníkovi.');
});

test('Smazání účastníka jej odstraní z databáze', function (\PDO $pdo) {
    $repo = new ParticipantRepository($pdo);
    $p = $repo->create(['name' => 'Ke smazání', 'email' => 'smazat@example.com']);
    $repo->delete($p->id);
    assertTrue($repo->find($p->id) === null, 'Smazaný účastník nesmí být dohledatelný.');
});
