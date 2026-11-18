<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

use App\Models\Enrollment;
// Import datového modelu Enrollment (potřebujeme jeho konstanty STATUS_*).
use App\Repositories\CourseRepository;
// Import repository pro kurzy (potřebné na vytvoření testovacího kurzu).
use App\Repositories\EnrollmentRepository;
// Import repository pro přihlášky - hlavní testovaná třída.
use App\Repositories\ParticipantRepository;
// Import repository pro účastníky (potřebné na vytvoření testovacího účastníka).
use App\Validation\ValidationException;
// Import vlastní výjimky, kterou testy ověřují.

/**
 * Pomocná funkce: vytvoří testovací kurz a vrátí jeho řádek (s obsazeností).
 */
function enrollmentTest_makeCourse(\PDO $pdo, int $capacity = 2): array
{
    // Pomocná funkce, aby se v každém testu nemusel psát stejný kód
    // pro vytvoření kurzu znovu a znovu.
    $repo = new CourseRepository($pdo);
    // Vytvoří repository nad testovací databází.
    $course = $repo->create([
        'name' => 'Testovací kurz',
        'code' => 'ENR-' . bin2hex(random_bytes(4)),
        // Náhodný kód (8 hex znaků), aby se testy nekolidovaly, pokud
        // by se v jednom testu vytvářelo víc kurzů.
        'capacity' => $capacity,
        // Kapacita je parametr funkce - výchozí hodnota 2.
        'starts_at' => '2026-01-01T10:00',
    ]);

    return $repo->find($course->id);
    // Vrátí celý řádek z databáze (ne jen objekt Course) - obsahuje
    // i dopočítané enrolled_count/free_count.
}

/**
 * Pomocná funkce: vytvoří testovacího účastníka a vrátí jeho řádek.
 */
function enrollmentTest_makeParticipant(\PDO $pdo, string $email): array
{
    // Obdoba výše, jen pro účastníka - e-mail je parametr, aby šlo
    // v jednom testu vytvořit víc různých účastníků.
    $repo = new ParticipantRepository($pdo);
    $p = $repo->create(['name' => 'Účastník', 'email' => $email]);

    return $repo->find($p->id);
    // Vrátí celý řádek z databáze.
}

test('Přihlášení účastníka do kurzu se uloží se stavem enrolled', function (\PDO $pdo) {
    // Základní test - ověří, že nová přihláška vznikne se správným
    // počátečním stavem a že se započítá do aktivních přihlášek.
    $course = enrollmentTest_makeCourse($pdo);
    // Vytvoří testovací kurz (výchozí kapacita 2).
    $participant = enrollmentTest_makeParticipant($pdo, 'a@example.com');
    // Vytvoří testovacího účastníka.
    $repo = new EnrollmentRepository($pdo);
    // Vytvoří repository pro přihlášky.

    $enrollment = $repo->enroll((int) $course['id'], (int) $participant['id']);
    // Přihlásí účastníka do kurzu.

    assertSame(Enrollment::STATUS_ENROLLED, $enrollment->status);
    // Nová přihláška musí mít stav 'enrolled'.
    assertSame(1, $repo->countActive((int) $course['id']));
    // Kurz musí mít přesně 1 aktivní přihlášku.
});

test('Účastník se nesmí přihlásit dvakrát do stejného kurzu', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "Účastník se nesmí
    // přihlásit dvakrát."
    $course = enrollmentTest_makeCourse($pdo);
    $participant = enrollmentTest_makeParticipant($pdo, 'b@example.com');
    $repo = new EnrollmentRepository($pdo);

    $repo->enroll((int) $course['id'], (int) $participant['id']);
    // První přihlášení musí proběhnout bez problémů.

    assertThrows(ValidationException::class, function () use ($repo, $course, $participant) {
        // Druhé přihlášení STEJNÉHO účastníka do STEJNÉHO kurzu musí
        // vyhodit ValidationException.
        $repo->enroll((int) $course['id'], (int) $participant['id']);
    }, 'Druhé přihlášení stejného účastníka do stejného kurzu musí být odmítnuto.');
});

test('Počet aktivních přihlášek nesmí překročit kapacitu kurzu', function (\PDO $pdo) {
    // Test POVINNÉHO VALIDAČNÍHO PRAVIDLA ZE ZADÁNÍ "Počet aktivních
    // přihlášek nesmí překročit kapacitu."
    $course = enrollmentTest_makeCourse($pdo, capacity: 2);
    // Kurz s kapacitou přesně 2 místa.
    $repo = new EnrollmentRepository($pdo);

    $p1 = enrollmentTest_makeParticipant($pdo, 'c1@example.com');
    // Tři různí účastníci - víc, než je kapacita kurzu.
    $p2 = enrollmentTest_makeParticipant($pdo, 'c2@example.com');
    $p3 = enrollmentTest_makeParticipant($pdo, 'c3@example.com');

    $repo->enroll((int) $course['id'], (int) $p1['id']);
    // První dva se musí přihlásit v pořádku (kapacita 2 ještě nedosažena).
    $repo->enroll((int) $course['id'], (int) $p2['id']);

    assertThrows(ValidationException::class, function () use ($repo, $course, $p3) {
        // Třetí účastník už by kapacitu překročil - musí být odmítnut.
        $repo->enroll((int) $course['id'], (int) $p3['id']);
    }, 'Přihlášení nad kapacitu kurzu musí být odmítnuto.');

    assertSame(2, $repo->countActive((int) $course['id']));
    // Aktivních přihlášek musí zůstat přesně 2 (odmítnutý pokus se
    // nesmí nijak projevit).
});

test('Zrušení přihlášky uvolní místo v kapacitě pro jiného účastníka', function (\PDO $pdo) {
    // Test DOMÁCÍ PRÁCE ZE ZADÁNÍ "Dokončete ... hlídání kapacity" -
    // ověřuje, že zrušení přihlášky skutečně uvolní místo.
    $course = enrollmentTest_makeCourse($pdo, capacity: 1);
    // Kurz s kapacitou jen 1 místo.
    $repo = new EnrollmentRepository($pdo);
    $p1 = enrollmentTest_makeParticipant($pdo, 'd1@example.com');
    $p2 = enrollmentTest_makeParticipant($pdo, 'd2@example.com');

    $e1 = $repo->enroll((int) $course['id'], (int) $p1['id']);
    // První účastník zabere jediné místo.

    assertThrows(ValidationException::class, function () use ($repo, $course, $p2) {
        // Druhý účastník se už nevejde - kapacita 1 je plná.
        $repo->enroll((int) $course['id'], (int) $p2['id']);
    }, 'Kurz s kapacitou 1 musí odmítnout druhého účastníka, pokud je první aktivní.');

    $repo->cancel($e1->id);
    // Zruší přihlášku prvního účastníka.
    assertSame(Enrollment::STATUS_CANCELLED, $repo->find($e1->id)['status']);
    // Stav musí být 'cancelled'.
    assertSame(0, $repo->countActive((int) $course['id']));
    // Po zrušení musí být 0 aktivních přihlášek (místo se uvolnilo).

    $e2 = $repo->enroll((int) $course['id'], (int) $p2['id']);
    // Teď už se druhý účastník musí vejít.
    assertSame(Enrollment::STATUS_ENROLLED, $e2->status);
    // Jeho nová přihláška musí mít stav 'enrolled'.
});

test('Účastník se může po zrušení přihlášky znovu přihlásit do kurzu', function (\PDO $pdo) {
    // Test toho, že zrušení NENÍ "navždy" - po zrušení je možné se
    // do stejného kurzu znovu přihlásit (omezení je jen na AKTIVNÍ
    // duplicitu, ne na historii).
    $course = enrollmentTest_makeCourse($pdo, capacity: 3);
    $repo = new EnrollmentRepository($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'e1@example.com');

    $first = $repo->enroll((int) $course['id'], (int) $p['id']);
    // První přihlášení.
    $repo->cancel($first->id);
    // Zrušení té samé přihlášky.

    $second = $repo->enroll((int) $course['id'], (int) $p['id']);
    // Nové přihlášení TÉHOŽ účastníka do TÉHOŽ kurzu - musí projít,
    // protože ta předchozí přihláška už není aktivní.
    assertSame(Enrollment::STATUS_ENROLLED, $second->status);
    // Nová přihláška musí mít stav 'enrolled'.
    assertSame(1, $repo->countActive((int) $course['id']));
    // Aktivní je teď jen ta nová (ta zrušená se nepočítá).
});

test('Přihlášku lze označit jako absolvovanou a uvolní místo v kapacitě', function (\PDO $pdo) {
    // Test DOMÁCÍ PRÁCE ZE ZADÁNÍ "Přidejte stavy enrolled, cancelled
    // a completed" - konkrétně přechod do stavu 'completed'.
    $course = enrollmentTest_makeCourse($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'f1@example.com');
    $repo = new EnrollmentRepository($pdo);

    $e = $repo->enroll((int) $course['id'], (int) $p['id']);
    // Přihlásí účastníka.
    $repo->complete($e->id);
    // Oznaci přihlášku jako absolvovanou.

    $found = $repo->find($e->id);
    // Znovu ji načte z databáze.
    assertSame(Enrollment::STATUS_COMPLETED, $found['status']);
    // Stav musí být 'completed'.
    assertSame(0, $repo->countActive((int) $course['id']));
    // Dokončená přihláška se už nepočítá do AKTIVNÍCH - místo se uvolní.
});

test('Zrušit lze jen aktivní (enrolled) přihlášku', function (\PDO $pdo) {
    // Test toho, že přechody mezi stavy mají svá pravidla - z 'completed'
    // už nejde přihlášku "zrušit" (to by nedávalo smysl).
    $course = enrollmentTest_makeCourse($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'g1@example.com');
    $repo = new EnrollmentRepository($pdo);

    $e = $repo->enroll((int) $course['id'], (int) $p['id']);
    // Přihlásí účastníka (stav 'enrolled').
    $repo->complete($e->id);
    // Oznaci jako absolvovanou (stav 'completed').

    assertThrows(ValidationException::class, function () use ($repo, $e) {
        // Pokus o zrušení JIŽ ABSOLVOVANÉ přihlášky musí být odmítnut.
        $repo->cancel($e->id);
    }, 'Absolvovanou přihlášku nelze zrušit.');
});
