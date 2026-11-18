<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Controllers;
// Namespace App\Controllers - soubor src/Controllers/EnrollmentController.php.

use App\Repositories\EnrollmentRepository;
// Import repository pro přihlášky.
use App\Validation\ValidationException;
// Import vlastní výjimky pro validační chyby.
use PDO;
// Import vestavěné třídy PDO.

/**
 * Změny stavu přihlášky: enrolled -> cancelled, enrolled -> completed.
 */
final class EnrollmentController
{
    // Malý controller zodpovědný jen za změny stavu existující přihlášky
    // (samotné vytvoření přihlášky řeší CourseController::enroll(),
    // protože logicky patří k detailu kurzu).

    private EnrollmentRepository $enrollments;
    // Vlastnost pro práci s přihláškami.

    public function __construct(PDO $pdo)
    {
        // Konstruktor vytvoří repository z databázového připojení.
        $this->enrollments = new EnrollmentRepository($pdo);
    }

    public function cancel(string $id): void
    {
        // Zpracovává POST /enrollments/{id}/cancel - zrušení přihlášky.
        $row = $this->enrollments->find((int) $id);
        // Nejdřív si přihlášku najde, abychom znali course_id (kam se
        // po akci vrátit přesměrováním).
        $courseId = $row['course_id'] ?? null;
        // Pokud přihláška neexistuje, $row je null a $courseId zůstane null.

        try {
            $this->enrollments->cancel((int) $id);
            // Zavolá repository - změní stav na 'cancelled', pokud je
            // to z aktuálního stavu povolený přechod.
            flash('success', 'Přihláška byla zrušena.');
        } catch (ValidationException $e) {
            // Pokud přechod nebyl povolen (např. přihláška už je
            // 'completed' nebo neexistuje).
            flash('error', implode(' ', $e->errors()));
        }

        redirect('/courses/' . ($courseId ?? ''));
        // Vrátí uživatele zpět na detail kurzu, odkud akci spustil.
    }

    public function complete(string $id): void
    {
        // Zpracovává POST /enrollments/{id}/complete - oznacení jako
        // absolvované.
        $row = $this->enrollments->find((int) $id);
        // Najde přihlášku, abychom znali course_id.
        $courseId = $row['course_id'] ?? null;

        try {
            $this->enrollments->complete((int) $id);
            // Zavolá repository - změní stav na 'completed'.
            flash('success', 'Přihláška byla označena jako absolvovaná.');
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors()));
        }

        redirect('/courses/' . ($courseId ?? ''));
        // Vrátí uživatele zpět na detail kurzu.
    }
}
