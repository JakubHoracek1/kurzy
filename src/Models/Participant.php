<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Datový model Participant: name, email.
 */
final class Participant
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
        );
    }
}
