<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Datový model Course: name, code, capacity, starts_at.
 */
final class Course
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly int $capacity,
        public readonly string $startsAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            code: (string) $row['code'],
            capacity: (int) $row['capacity'],
            startsAt: (string) $row['starts_at'],
        );
    }
}
