<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Participant;
use App\Validation\ValidationException;
use PDO;

/**
 * CRUD a validace pro Participant.
 *
 * Validační pravidla ze zadání:
 *  - E-mail účastníka je platný a jedinečný.
 */
final class ParticipantRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.*,
                (SELECT COUNT(*) FROM enrollments e
                    WHERE e.participant_id = p.id AND e.status = 'enrolled') AS active_enrollments
             FROM participants p
             ORDER BY p.name ASC"
        );

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM participants WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Vrátí účastníky, kteří v daném kurzu NEMAJÍ aktivní (status = enrolled)
     * přihlášku - tedy ty, které lze (znovu) přihlásit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allNotActivelyEnrolledInCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.* FROM participants p
             WHERE p.id NOT IN (
                 SELECT e.participant_id FROM enrollments e
                 WHERE e.course_id = :course_id AND e.status = 'enrolled'
             )
             ORDER BY p.name ASC"
        );
        $stmt->execute(['course_id' => $courseId]);

        return $stmt->fetchAll();
    }

    public function create(array $data): Participant
    {
        $clean = $this->validate($data);

        $stmt = $this->pdo->prepare('INSERT INTO participants (name, email) VALUES (:name, :email)');
        $stmt->execute($clean);
        $id = (int) $this->pdo->lastInsertId();

        return new Participant($id, $clean['name'], $clean['email']);
    }

    public function update(int $id, array $data): Participant
    {
        $clean = $this->validate($data, excludeId: $id);

        $stmt = $this->pdo->prepare('UPDATE participants SET name = :name, email = :email WHERE id = :id');
        $stmt->execute($clean + ['id' => $id]);

        return new Participant($id, $clean['name'], $clean['email']);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM participants WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM participants WHERE email = :email';
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * @return array{name: string, email: string}
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Jméno účastníka je povinné.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors[] = 'E-mail účastníka je povinný.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'E-mail účastníka není platný.';
        } elseif ($this->emailExists($email, $excludeId)) {
            $errors[] = 'E-mail účastníka musí být jedinečný - tento e-mail už je zaregistrovaný.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
    }
}
