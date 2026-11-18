<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Controllers;
// Namespace App\Controllers - soubor src/Controllers/ParticipantController.php.

use App\Repositories\ParticipantRepository;
// Import repository pro účastníky.
use App\Validation\ValidationException;
// Import vlastní výjimky pro validační chyby.
use App\View;
// Import rendereru šablon.
use PDO;
// Import vestavěné třídy PDO.

/**
 * CRUD účastníků.
 */
final class ParticipantController
{
    // Controller analogický CourseController, ale pro účastníky - jen
    // bez přihlašování (to řeší CourseController a EnrollmentController).

    private ParticipantRepository $participants;
    // Vlastnost pro práci s účastníky.

    public function __construct(PDO $pdo)
    {
        // Konstruktor vytvoří repository z databázového připojení.
        $this->participants = new ParticipantRepository($pdo);
    }

    public function index(): void
    {
        // Zpracovává GET /participants - seznam všech účastníků.
        View::render('participants/index', [
            'title' => 'Účastníci',
            'participants' => $this->participants->all(),
            // Zavolá repository přímo v argumentu - načte všechny
            // účastníky a předá je do šablony.
        ]);
    }

    public function create(): void
    {
        // Zpracovává GET /participants/new - prázdný formulář.
        View::render('participants/form', [
            'title' => 'Nový účastník',
            'participant' => null,
            // null = nejde o editaci.
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(): void
    {
        // Zpracovává POST /participants - uložení nového účastníka.
        try {
            $this->participants->create($_POST);
            // Validace + uložení - při chybě vyhodí ValidationException.
            flash('success', 'Účastník byl vytvořen.');
            redirect('/participants');
        } catch (ValidationException $e) {
            // Při neplatných datech (prázdné jméno, neplatný/duplicitní
            // e-mail) znovu zobrazí formulář s chybami.
            View::render('participants/form', [
                'title' => 'Nový účastník',
                'participant' => null,
                'errors' => $e->errors(),
                'old' => $_POST,
            ], 422);
        }
    }

    public function edit(string $id): void
    {
        // Zpracovává GET /participants/{id}/edit.
        $participant = $this->participants->find((int) $id);
        // Najde účastníka podle ID.
        if ($participant === null) {
            // Účastník neexistuje.
            $this->notFound();
            return;
        }

        View::render('participants/form', [
            // Formulář předvyplněný údaji účastníka.
            'title' => 'Upravit účastníka',
            'participant' => $participant,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function update(string $id): void
    {
        // Zpracovává POST /participants/{id} - uložení úpravy.
        $participant = $this->participants->find((int) $id);
        if ($participant === null) {
            $this->notFound();
            return;
        }

        try {
            $this->participants->update((int) $id, $_POST);
            flash('success', 'Účastník byl upraven.');
            redirect('/participants');
        } catch (ValidationException $e) {
            View::render('participants/form', [
                'title' => 'Upravit účastníka',
                'participant' => $participant,
                'errors' => $e->errors(),
                'old' => $_POST,
            ], 422);
        }
    }

    public function destroy(string $id): void
    {
        // Zpracovává POST /participants/{id}/delete.
        $this->participants->delete((int) $id);
        // Smaže účastníka (a kaskádově jeho přihlášky).
        flash('success', 'Účastník byl smazán.');
        redirect('/participants');
    }

    private function notFound(): void
    {
        // Pomocná metoda pro zobrazení 404 stránky.
        View::render('errors/404', ['title' => 'Nenalezeno'], 404);
    }
}
