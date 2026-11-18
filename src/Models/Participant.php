<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Models;
// Namespace App\Models - soubor src/Models/Participant.php.

/**
 * Datový model Participant: name, email.
 */
final class Participant
{
    // Datová třída reprezentující jeden řádek tabulky "participants".

    public function __construct(
        public readonly ?int $id,
        // ID účastníka - null pro nový, ještě neuložený objekt.
        public readonly string $name,
        // Jméno účastníka (povinné pole ze zadání).
        public readonly string $email,
        // E-mail účastníka - musí být platný a jedinečný (validuje se
        // v ParticipantRepository, ne tady - tahle třída jen uchovává data).
    ) {
        // Prázdné tělo - přiřazení řeší property promotion výše.
    }

    public static function fromRow(array $row): self
    {
        // Vytvoří objekt Participant z řádku z databáze.
        return new self(
            id: (int) $row['id'],
            // Přetypuje ID na int (z DB může přijít jako string).
            name: (string) $row['name'],
            // Přetypuje jméno na string.
            email: (string) $row['email'],
            // Přetypuje e-mail na string.
        );
    }
}
