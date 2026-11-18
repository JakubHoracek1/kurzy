<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Validation;
// Namespace App\Validation - soubor src/Validation/ValidationException.php.

/**
 * Výjimka nesoucí seznam chyb validace (validační pravidla ze zadání).
 */
final class ValidationException extends \RuntimeException
{
    // Vlastní výjimka, která dědí z vestavěné třídy RuntimeException -
    // díky tomu se dá chytit běžným "catch" a chová se jako normální
    // PHP výjimka, ale navíc nese seznam konkrétních validačních chyb.

    /** @var string[] */
    private array $errors;
    // Soukromá vlastnost - pole textových chybových hlášek
    // (např. "Kód kurzu musí být jedinečný.").

    /**
     * @param string[] $errors
     */
    public function __construct(array $errors)
    {
        // Konstruktor přijímá pole chyb, které se budou zobrazovat
        // ve formuláři nebo jako flash zpráva.
        $this->errors = $errors;
        // Uloží pole chyb do vlastnosti objektu.
        parent::__construct(implode(' ', $errors));
        // Zavolá konstruktor rodičovské třídy (RuntimeException) a jako
        // standardní text výjimky (getMessage()) mu předá všechny chyby
        // spojené do jednoho stringu oddělené mezerou.
    }

    /**
     * @return string[]
     */
    public function errors(): array
    {
        // Veřejná metoda pro přečtení seznamu jednotlivých chyb (ne jen
        // spojeného textu), aby šlo ve formuláři vypsat každou chybu
        // na samostatný řádek.
        return $this->errors;
    }
}
