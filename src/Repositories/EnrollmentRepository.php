<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Enrollment;
use App\Validation\ValidationException;
use PDO;

/**
 * Přihlašování účastníků do kurzů a hlídání kapacity.
 *
 * Validační pravidla ze zadání:
 *  - Účastník se nesmí přihlásit dvakrát (nesmí mít dvě aktivní přihlášky
 *    do stejného kurzu).
 *  - Počet aktivních přihlášek nesmí překročit kapacitu kurzu.
 *
 * Domácí práce:
 *  - Stavy: enrolled, cancelled, completed.
 */
final class EnrollmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Seznam přihlášených do kurzu (včetně jména/e-mailu účastníka),
     * od nejnovějších.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.name AS participant_name, p.email AS participant_email
             FROM enrollments e
             JOIN participants p ON p.id = e.participant_id
             WHERE e.course_id = :course_id
             ORDER BY e.enrolled_at DESC, e.id DESC'
        );
        $stmt->execute(['course_id' => $courseId]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM enrollments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Počet aktivních (status = enrolled) přihlášek v kurzu.
     */
    public function countActive(int $courseId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM enrollments WHERE course_id = :course_id AND status = 'enrolled'"
        );
        $stmt->execute(['course_id' => $courseId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Přihlásí účastníka do kurzu.
     *
     * Ověří:
     *  - kurz a účastník existují,
     *  - účastník ještě nemá v kurzu aktivní přihlášku (nesmí se přihlásit dvakrát),
     *  - kapacita kurzu není naplněná.
     *
     * Celá operace běží v transakci (BEGIN IMMEDIATE), aby dvě souběžná
     * přihlášení nemohla kapacitu překročit.
     */
    public function enroll(int $courseId, int $participantId): Enrollment
    {
        $this->pdo->exec('BEGIN IMMEDIATE');

        try {
            $course = $this->pdo->prepare('SELECT * FROM courses WHERE id = :id');
            $course->execute(['id' => $courseId]);
            $courseRow = $course->fetch();
            if ($courseRow === false) {
                throw new ValidationException(['Vybraný kurz neexistuje.']);
            }

            $participant = $this->pdo->prepare('SELECT * FROM participants WHERE id = :id');
            $participant->execute(['id' => $participantId]);
            if ($participant->fetch() === false) {
                throw new ValidationException(['Vybraný účastník neexistuje.']);
            }

            $existing = $this->pdo->prepare(
                "SELECT id FROM enrollments
                 WHERE course_id = :course_id AND participant_id = :participant_id AND status = 'enrolled'"
            );
            $existing->execute(['course_id' => $courseId, 'participant_id' => $participantId]);
            if ($existing->fetch() !== false) {
                throw new ValidationException(['Účastník je do tohoto kurzu již přihlášen.']);
            }

            $activeCount = $this->countActive($courseId);
            if ($activeCount >= (int) $courseRow['capacity']) {
                throw new ValidationException(['Kurz je plně obsazen, kapacitu nelze překročit.']);
            }

            $enrolledAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

            $insert = $this->pdo->prepare(
                "INSERT INTO enrollments (course_id, participant_id, enrolled_at, status)
                 VALUES (:course_id, :participant_id, :enrolled_at, 'enrolled')"
            );
            $insert->execute([
                'course_id' => $courseId,
                'participant_id' => $participantId,
                'enrolled_at' => $enrolledAt,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            $this->pdo->exec('COMMIT');

            return new Enrollment($id, $courseId, $participantId, $enrolledAt, Enrollment::STATUS_ENROLLED);
        } catch (\Throwable $e) {
            $this->pdo->exec('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Zruší přihlášku (status -> cancelled). Lze zrušit jen aktivní přihlášku.
     * Uvolní tím místo v kapacitě kurzu.
     */
    public function cancel(int $enrollmentId): void
    {
        $this->transitionStatus($enrollmentId, Enrollment::STATUS_ENROLLED, Enrollment::STATUS_CANCELLED);
    }

    /**
     * Oznaci přihlášku jako absolvovanou (status -> completed). Lze provést
     * jen z aktivního stavu.
     */
    public function complete(int $enrollmentId): void
    {
        $this->transitionStatus($enrollmentId, Enrollment::STATUS_ENROLLED, Enrollment::STATUS_COMPLETED);
    }

    private function transitionStatus(int $enrollmentId, string $fromStatus, string $toStatus): void
    {
        $row = $this->find($enrollmentId);
        if ($row === null) {
            throw new ValidationException(['Přihláška neexistuje.']);
        }
        if ($row['status'] !== $fromStatus) {
            throw new ValidationException(["Tento přechod stavu není povolen (aktuální stav: {$row['status']})."]);
        }

        $stmt = $this->pdo->prepare('UPDATE enrollments SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $toStatus, 'id' => $enrollmentId]);
    }
}
