<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Course;
use App\Validation\ValidationException;
use PDO;

/**
 * CRUD a validace pro Course.
 *
 * Validační pravidla ze zadání:
 *  - Kód kurzu je jedinečný.
 *  - Kapacita je celé číslo větší než 0.
 */
final class CourseRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Vrátí kurzy včetně počtu aktivních přihlášek a volné kapacity,
     * volitelně filtrované podle termínu konání a volných míst.
     *
     * $filters:
     *   from      - string|null (datum od, formát Y-m-d\TH:i nebo Y-m-d)
     *   to        - string|null (datum do)
     *   only_free - bool (zobrazit jen kurzy s volným místem)
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql = "SELECT c.*,
                    (SELECT COUNT(*) FROM enrollments e
                        WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_count
                FROM courses c
                WHERE 1 = 1";
        $params = [];

        if (!empty($filters['from'])) {
            $sql .= ' AND c.starts_at >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND c.starts_at <= :to';
            $params['to'] = $filters['to'];
        }

        $sql .= ' ORDER BY c.starts_at ASC, c.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['enrolled_count'] = (int) $row['enrolled_count'];
            $row['capacity'] = (int) $row['capacity'];
            $row['free_count'] = max(0, $row['capacity'] - $row['enrolled_count']);
        }
        unset($row);

        if (!empty($filters['only_free'])) {
            $rows = array_values(array_filter($rows, static fn (array $r) => $r['free_count'] > 0));
        }

        return $rows;
    }

    /**
     * Najde kurz podle ID včetně obsazenosti. Vrátí null, pokud neexistuje.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                (SELECT COUNT(*) FROM enrollments e
                    WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_count
             FROM courses c WHERE c.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $row['enrolled_count'] = (int) $row['enrolled_count'];
        $row['capacity'] = (int) $row['capacity'];
        $row['free_count'] = max(0, $row['capacity'] - $row['enrolled_count']);

        return $row;
    }

    /**
     * Vytvoří nový kurz. Validuje vstup a vyhodí ValidationException při chybě.
     *
     * @param array{name?: string, code?: string, capacity?: mixed, starts_at?: string} $data
     */
    public function create(array $data): Course
    {
        $clean = $this->validate($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (name, code, capacity, starts_at) VALUES (:name, :code, :capacity, :starts_at)'
        );
        $stmt->execute($clean);
        $id = (int) $this->pdo->lastInsertId();

        return new Course($id, $clean['name'], $clean['code'], (int) $clean['capacity'], $clean['starts_at']);
    }

    /**
     * Upraví existující kurz. Validuje vstup a vyhodí ValidationException při chybě.
     */
    public function update(int $id, array $data): Course
    {
        $clean = $this->validate($data, excludeId: $id);

        $stmt = $this->pdo->prepare(
            'UPDATE courses SET name = :name, code = :code, capacity = :capacity, starts_at = :starts_at WHERE id = :id'
        );
        $stmt->execute($clean + ['id' => $id]);

        return new Course($id, $clean['name'], $clean['code'], (int) $clean['capacity'], $clean['starts_at']);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM courses WHERE code = :code';
        $params = ['code' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }

    /**
     * Ověří validační pravidla a vrátí "očištěná" data připravená pro uložení.
     *
     * @return array{name: string, code: string, capacity: int, starts_at: string}
     */
    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Název kurzu je povinný.';
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $errors[] = 'Kód kurzu je povinný.';
        } elseif ($this->codeExists($code, $excludeId)) {
            $errors[] = 'Kód kurzu musí být jedinečný - tento kód už existuje.';
        }

        $capacityRaw = $data['capacity'] ?? null;
        $capacity = null;
        if ($capacityRaw === null || $capacityRaw === '' ||
            filter_var($capacityRaw, FILTER_VALIDATE_INT) === false) {
            $errors[] = 'Kapacita musí být celé číslo větší než 0.';
        } else {
            $capacity = (int) $capacityRaw;
            if ($capacity <= 0) {
                $errors[] = 'Kapacita musí být celé číslo větší než 0.';
            }
        }

        $startsAt = trim((string) ($data['starts_at'] ?? ''));
        if ($startsAt === '') {
            $errors[] = 'Termín konání kurzu je povinný.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'code' => $code,
            'capacity' => $capacity,
            'starts_at' => $startsAt,
        ];
    }
}
