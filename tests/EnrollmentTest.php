<?php

declare(strict_types=1);

use App\Models\Enrollment;
use App\Repositories\CourseRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\ParticipantRepository;
use App\Validation\ValidationException;

/**
 * Pomocná funkce: vytvoří testovací kurz a vrátí jeho řádek (s obsazeností).
 */
function enrollmentTest_makeCourse(\PDO $pdo, int $capacity = 2): array
{
    $repo = new CourseRepository($pdo);
    $course = $repo->create([
        'name' => 'Testovací kurz',
        'code' => 'ENR-' . bin2hex(random_bytes(4)),
        'capacity' => $capacity,
        'starts_at' => '2026-01-01T10:00',
    ]);

    return $repo->find($course->id);
}

/**
 * Pomocná funkce: vytvoří testovacího účastníka a vrátí jeho řádek.
 */
function enrollmentTest_makeParticipant(\PDO $pdo, string $email): array
{
    $repo = new ParticipantRepository($pdo);
    $p = $repo->create(['name' => 'Účastník', 'email' => $email]);

    return $repo->find($p->id);
}

test('Přihlášení účastníka do kurzu se uloží se stavem enrolled', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo);
    $participant = enrollmentTest_makeParticipant($pdo, 'a@example.com');
    $repo = new EnrollmentRepository($pdo);

    $enrollment = $repo->enroll((int) $course['id'], (int) $participant['id']);

    assertSame(Enrollment::STATUS_ENROLLED, $enrollment->status);
    assertSame(1, $repo->countActive((int) $course['id']));
});

test('Účastník se nesmí přihlásit dvakrát do stejného kurzu', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo);
    $participant = enrollmentTest_makeParticipant($pdo, 'b@example.com');
    $repo = new EnrollmentRepository($pdo);

    $repo->enroll((int) $course['id'], (int) $participant['id']);

    assertThrows(ValidationException::class, function () use ($repo, $course, $participant) {
        $repo->enroll((int) $course['id'], (int) $participant['id']);
    }, 'Druhé přihlášení stejného účastníka do stejného kurzu musí být odmítnuto.');
});

test('Počet aktivních přihlášek nesmí překročit kapacitu kurzu', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo, capacity: 2);
    $repo = new EnrollmentRepository($pdo);

    $p1 = enrollmentTest_makeParticipant($pdo, 'c1@example.com');
    $p2 = enrollmentTest_makeParticipant($pdo, 'c2@example.com');
    $p3 = enrollmentTest_makeParticipant($pdo, 'c3@example.com');

    $repo->enroll((int) $course['id'], (int) $p1['id']);
    $repo->enroll((int) $course['id'], (int) $p2['id']);

    assertThrows(ValidationException::class, function () use ($repo, $course, $p3) {
        $repo->enroll((int) $course['id'], (int) $p3['id']);
    }, 'Přihlášení nad kapacitu kurzu musí být odmítnuto.');

    assertSame(2, $repo->countActive((int) $course['id']));
});

test('Zrušení přihlášky uvolní místo v kapacitě pro jiného účastníka', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo, capacity: 1);
    $repo = new EnrollmentRepository($pdo);
    $p1 = enrollmentTest_makeParticipant($pdo, 'd1@example.com');
    $p2 = enrollmentTest_makeParticipant($pdo, 'd2@example.com');

    $e1 = $repo->enroll((int) $course['id'], (int) $p1['id']);

    assertThrows(ValidationException::class, function () use ($repo, $course, $p2) {
        $repo->enroll((int) $course['id'], (int) $p2['id']);
    }, 'Kurz s kapacitou 1 musí odmítnout druhého účastníka, pokud je první aktivní.');

    $repo->cancel($e1->id);
    assertSame(Enrollment::STATUS_CANCELLED, $repo->find($e1->id)['status']);
    assertSame(0, $repo->countActive((int) $course['id']));

    $e2 = $repo->enroll((int) $course['id'], (int) $p2['id']);
    assertSame(Enrollment::STATUS_ENROLLED, $e2->status);
});

test('Účastník se může po zrušení přihlášky znovu přihlásit do kurzu', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo, capacity: 3);
    $repo = new EnrollmentRepository($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'e1@example.com');

    $first = $repo->enroll((int) $course['id'], (int) $p['id']);
    $repo->cancel($first->id);

    $second = $repo->enroll((int) $course['id'], (int) $p['id']);
    assertSame(Enrollment::STATUS_ENROLLED, $second->status);
    assertSame(1, $repo->countActive((int) $course['id']));
});

test('Přihlášku lze označit jako absolvovanou a uvolní místo v kapacitě', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'f1@example.com');
    $repo = new EnrollmentRepository($pdo);

    $e = $repo->enroll((int) $course['id'], (int) $p['id']);
    $repo->complete($e->id);

    $found = $repo->find($e->id);
    assertSame(Enrollment::STATUS_COMPLETED, $found['status']);
    assertSame(0, $repo->countActive((int) $course['id']));
});

test('Zrušit lze jen aktivní (enrolled) přihlášku', function (\PDO $pdo) {
    $course = enrollmentTest_makeCourse($pdo);
    $p = enrollmentTest_makeParticipant($pdo, 'g1@example.com');
    $repo = new EnrollmentRepository($pdo);

    $e = $repo->enroll((int) $course['id'], (int) $p['id']);
    $repo->complete($e->id);

    assertThrows(ValidationException::class, function () use ($repo, $e) {
        $repo->cancel($e->id);
    }, 'Absolvovanou přihlášku nelze zrušit.');
});
