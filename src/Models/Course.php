<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Models;
// Namespace App\Models - soubor leží v src/Models/Course.php.

/**
 * Datový model Course: name, code, capacity, starts_at.
 */
final class Course
{
    // Jednoduchá datová třída (tzv. DTO - Data Transfer Object) reprezentující
    // jeden řádek tabulky "courses". Nemá žádnou logiku, jen uchovává data.

    public function __construct(
        // PHP "constructor property promotion" - parametry konstruktoru
        // se automaticky stanou veřejnými vlastnostmi objektu, nemusí se
        // psát zvlášť $this->id = $id; pro každou vlastnost.
        public readonly ?int $id,
        // ID kurzu v databázi - readonly znamená, že se po vytvoření objektu
        // nedá změnit. Nullable (?int), protože nový (ještě neuložený)
        // kurz ID nemá.
        public readonly string $name,
        // Název kurzu (povinné pole ze zadání).
        public readonly string $code,
        // Unikátní kód kurzu (povinné pole ze zadání).
        public readonly int $capacity,
        // Kapacita kurzu - celé číslo (povinné pole ze zadání).
        public readonly string $startsAt,
        // Termín konání kurzu, uložený jako text (ISO formát data a času).
    ) {
        // Prázdné tělo konstruktoru - všechno přiřazení proběhlo
        // automaticky díky "property promotion" výše.
    }

    public static function fromRow(array $row): self
    {
        // Pomocná "továrna" (factory metoda), která z asociativního pole
        // (řádku vráceného z databáze přes PDO) vytvoří objekt Course.
        return new self(
            // "self" znamená aktuální třídu (Course) - vytvoří novou instanci.
            id: (int) $row['id'],
            // Jmenný argument (named argument) - čitelnější než pozice;
            // (int) přetypuje hodnotu z databáze (může přijít jako string).
            name: (string) $row['name'],
            code: (string) $row['code'],
            capacity: (int) $row['capacity'],
            startsAt: (string) $row['starts_at'],
            // starts_at (s podtržítkem, jak je ve sloupci DB) se mapuje
            // na startsAt (camelCase, jak je v PHP objektu).
        );
    }
}
