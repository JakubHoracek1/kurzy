<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Models;
// Namespace App\Models - soubor src/Models/Enrollment.php.

/**
 * Datový model Enrollment: course_id, participant_id, enrolled_at, status.
 * Povolené stavy: enrolled, cancelled, completed.
 */
final class Enrollment
{
    // Datová třída reprezentující jeden řádek tabulky "enrollments" -
    // tedy jednu přihlášku účastníka do kurzu.

    public const STATUS_ENROLLED = 'enrolled';
    // Konstanta pro stav "přihlášen" - aktivní přihláška, počítá se
    // do obsazenosti kurzu.
    public const STATUS_CANCELLED = 'cancelled';
    // Konstanta pro stav "zrušeno" - přihláška zrušená, uvolňuje
    // místo v kapacitě kurzu.
    public const STATUS_COMPLETED = 'completed';
    // Konstanta pro stav "absolvováno" - účastník kurz dokončil.

    /** @var string[] */
    public const STATUSES = [
        // Pole všech povolených stavů na jednom místě - pro případ,
        // že bychom je chtěli někde vypsat (např. v select boxu).
        self::STATUS_ENROLLED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    public function __construct(
        public readonly ?int $id,
        // ID přihlášky - null pro novou, ještě neuloženou přihlášku.
        public readonly int $courseId,
        // Cizí klíč na kurz (courses.id) - do jakého kurzu je přihláška.
        public readonly int $participantId,
        // Cizí klíč na účastníka (participants.id) - kdo se přihlásil.
        public readonly string $enrolledAt,
        // Datum a čas přihlášení (kdy přihláška vznikla).
        public readonly string $status,
        // Aktuální stav přihlášky - jedna z hodnot STATUS_* výše.
    ) {
        // Prázdné tělo - přiřazení řeší property promotion.
    }

    public static function fromRow(array $row): self
    {
        // Vytvoří objekt Enrollment z řádku z databáze.
        return new self(
            id: (int) $row['id'],
            courseId: (int) $row['course_id'],
            // course_id (sloupec DB) -> courseId (vlastnost objektu).
            participantId: (int) $row['participant_id'],
            // participant_id (sloupec DB) -> participantId (vlastnost objektu).
            enrolledAt: (string) $row['enrolled_at'],
            // enrolled_at (sloupec DB) -> enrolledAt (vlastnost objektu).
            status: (string) $row['status'],
        );
    }
}
