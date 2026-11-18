<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Datový model Enrollment: course_id, participant_id, enrolled_at, status.
 * Povolené stavy: enrolled, cancelled, completed.
 */
final class Enrollment
{
    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    /** @var string[] */
    public const STATUSES = [
        self::STATUS_ENROLLED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    public function __construct(
        public readonly ?int $id,
        public readonly int $courseId,
        public readonly int $participantId,
        public readonly string $enrolledAt,
        public readonly string $status,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            courseId: (int) $row['course_id'],
            participantId: (int) $row['participant_id'],
            enrolledAt: (string) $row['enrolled_at'],
            status: (string) $row['status'],
        );
    }
}
