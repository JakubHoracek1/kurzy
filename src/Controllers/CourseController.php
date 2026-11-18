<?php
// Otevírací PHP tag.

declare(strict_types=1);
// Striktní typová kontrola.

namespace App\Controllers;
// Namespace App\Controllers - soubor src/Controllers/CourseController.php.

use App\Repositories\CourseRepository;
// Import repository pro kurzy.
use App\Repositories\EnrollmentRepository;
// Import repository pro přihlášky (potřebné pro detail kurzu a přihlašování).
use App\Repositories\ParticipantRepository;
// Import repository pro účastníky (potřebné pro nabídku "koho přihlásit").
use App\Validation\ValidationException;
// Import vlastní výjimky pro zachytávání validačních chyb.
use App\View;
// Import rendereru šablon.
use PDO;
// Import vestavěné třídy PDO.

/**
 * CRUD kurzů, zobrazení obsazenosti a seznamu přihlášených,
 * filtr podle termínu a volných míst, přihlašování účastníků.
 */
final class CourseController
{
    // Controller = vrstva, která zpracovává HTTP požadavek (z routeru
    // v public/index.php), zavolá příslušné repository a vykreslí
    // odpovídající šablonu - "lepidlo" mezi webem a logikou.

    private CourseRepository $courses;
    // Vlastnost pro práci s kurzy.
    private EnrollmentRepository $enrollments;
    // Vlastnost pro práci s přihláškami.
    private ParticipantRepository $participants;
    // Vlastnost pro práci s účastníky.

    public function __construct(PDO $pdo)
    {
        // Konstruktor přijme databázové připojení a vytvoří si z něj
        // instance všech repository, které tento controller potřebuje.
        $this->courses = new CourseRepository($pdo);
        $this->enrollments = new EnrollmentRepository($pdo);
        $this->participants = new ParticipantRepository($pdo);
    }

    /** Seznam kurzů + filtr podle termínu a volných míst. */
    public function index(): void
    {
        // Zpracovává GET /courses - zobrazí seznam kurzů s možností filtrovat.
        $from = trim((string) ($_GET['from'] ?? ''));
        // Přečte filtr "od" z URL parametrů (např. ?from=2026-01-01).
        $to = trim((string) ($_GET['to'] ?? ''));
        // Přečte filtr "do".
        $onlyFree = !empty($_GET['only_free']);
        // Přečte checkbox "jen s volným místem" - !empty() vrátí true,
        // pokud parametr existuje a není "prázdný" (0, '', null).

        // Filtr "do" zadává uživatel jako datum (bez času) - aby zahrnoval
        // i kurzy konající se v daný den, porovnáváme do konce dne.
        $toForQuery = ($to !== '' && !str_contains($to, 'T')) ? $to . 'T23:59:59' : $to;
        // Pokud je "do" vyplněné a ještě neobsahuje čas (písmeno T jako
        // v ISO formátu data+času), přilepí k němu konec dne 23:59:59 -
        // jinak by se kurz konající se přesně v daný den vyřadil.

        $courses = $this->courses->all([
            // Zavolá repository s filtry, aby vrátila jen odpovídající kurzy.
            'from' => $from,
            'to' => $toForQuery,
            'only_free' => $onlyFree,
        ]);

        View::render('courses/index', [
            // Vykreslí šablonu views/courses/index.php a předá jí proměnné.
            'title' => 'Kurzy',
            'courses' => $courses,
            'filters' => ['from' => $from, 'to' => $to, 'only_free' => $onlyFree],
            // Do šablony se předávají PŮVODNÍ (ne upravené) hodnoty filtrů,
            // aby se ve formuláři zobrazilo přesně to, co uživatel zadal.
        ]);
    }

    /** Formulář pro nový kurz. */
    public function create(): void
    {
        // Zpracovává GET /courses/new - zobrazí prázdný formulář.
        View::render('courses/form', [
            'title' => 'Nový kurz',
            'course' => null,
            // null znamená "nejde o editaci" - formulář bude prázdný.
            'errors' => [],
            // Zatím žádné chyby (formulář se teprve zobrazuje).
            'old' => [],
            // Žádné dříve zadané hodnoty.
        ]);
    }

    /** Uložení nového kurzu. */
    public function store(): void
    {
        // Zpracovává POST /courses - uložení dat z formuláře nového kurzu.
        try {
            $this->courses->create($_POST);
            // Zavolá repository s daty z formuláře; pokud jsou neplatná,
            // create() vyhodí ValidationException.
            flash('success', 'Kurz byl vytvořen.');
            // Uloží úspěšnou zprávu pro zobrazení po přesměrování.
            redirect('/courses');
            // Přesměruje zpět na seznam kurzů.
        } catch (ValidationException $e) {
            // Pokud validace selhala.
            View::render('courses/form', [
                // Znovu zobrazí formulář, ale teď s chybami a s tím, co
                // uživatel zadal (aby to nemusel psát znovu).
                'title' => 'Nový kurz',
                'course' => null,
                'errors' => $e->errors(),
                'old' => $_POST,
            ], 422);
            // HTTP stav 422 = "Unprocessable Entity" (neplatná data).
        }
    }

    /** Formulář pro úpravu kurzu. */
    public function edit(string $id): void
    {
        // Zpracovává GET /courses/{id}/edit - $id přichází z routeru
        // (zachycená skupina z regulárního výrazu v public/index.php),
        // proto je to string, ne int.
        $course = $this->courses->find((int) $id);
        // Najde kurz podle ID (přetypovaného na int).
        if ($course === null) {
            // Kurz neexistuje.
            $this->notFound();
            // Zobrazí stránku 404.
            return;
            // Ukončí metodu, aby se dál nepokračovalo.
        }

        View::render('courses/form', [
            // Zobrazí formulář předvyplněný údaji nalezeného kurzu.
            'title' => 'Upravit kurz',
            'course' => $course,
            'errors' => [],
            'old' => [],
        ]);
    }

    /** Uložení úpravy kurzu. */
    public function update(string $id): void
    {
        // Zpracovává POST /courses/{id} - uložení upravených dat kurzu.
        $course = $this->courses->find((int) $id);
        // Ověří, že kurz s tímto ID existuje.
        if ($course === null) {
            $this->notFound();
            return;
        }

        try {
            $this->courses->update((int) $id, $_POST);
            // Zavolá repository s novými daty z formuláře.
            flash('success', 'Kurz byl upraven.');
            redirect('/courses');
        } catch (ValidationException $e) {
            // Při neplatných datech znovu zobrazí formulář s chybami.
            View::render('courses/form', [
                'title' => 'Upravit kurz',
                'course' => $course,
                'errors' => $e->errors(),
                'old' => $_POST,
            ], 422);
        }
    }

    /** Smazání kurzu (smaže i navázané přihlášky - ON DELETE CASCADE). */
    public function destroy(string $id): void
    {
        // Zpracovává POST /courses/{id}/delete.
        $this->courses->delete((int) $id);
        // Smaže kurz (a kaskádově i jeho přihlášky, díky nastavení v DB).
        flash('success', 'Kurz byl smazán.');
        redirect('/courses');
    }

    /** Detail kurzu: obsazenost, seznam přihlášených, formulář pro přihlášení. */
    public function show(string $id): void
    {
        // Zpracovává GET /courses/{id} - stránka detailu jednoho kurzu.
        $course = $this->courses->find((int) $id);
        // Najde kurz podle ID.
        if ($course === null) {
            $this->notFound();
            return;
        }

        $enrollments = $this->enrollments->listByCourse((int) $id);
        // Načte seznam všech přihlášek (ve všech stavech) pro tento kurz
        // - POŽADOVANÁ FUNKCE ZE ZADÁNÍ "Zobrazení ... seznamu přihlášených."
        $availableParticipants = $this->participants->allNotActivelyEnrolledInCourse((int) $id);
        // Načte účastníky, které lze (ještě/znovu) přihlásit - pro select box.

        View::render('courses/show', [
            'title' => 'Kurz: ' . $course['name'],
            'course' => $course,
            // $course obsahuje i enrolled_count/free_count (obsazenost)
            // - POŽADOVANÁ FUNKCE ZE ZADÁNÍ "Zobrazení obsazenosti."
            'enrollments' => $enrollments,
            'availableParticipants' => $availableParticipants,
        ]);
    }

    /** Přihlášení účastníka do kurzu (hlídá duplicitu a kapacitu). */
    public function enroll(string $id): void
    {
        // Zpracovává POST /courses/{id}/enroll - samo přihlášení.
        $participantId = (int) ($_POST['participant_id'] ?? 0);
        // Přečte ID vybraného účastníka z formuláře (select boxu);
        // pokud by chybělo, použije 0 (repository ho stejně odmítne
        // jako "neexistující účastník").

        try {
            $this->enrollments->enroll((int) $id, $participantId);
            // Zavolá hlavní metodu, která ověří duplicitu a kapacitu
            // a vytvoří novou přihlášku.
            flash('success', 'Účastník byl přihlášen.');
        } catch (ValidationException $e) {
            // Pokud přihlášení nebylo možné (duplicita nebo plná kapacita).
            flash('error', implode(' ', $e->errors()));
            // Uloží chybovou hlášku (spojenou do jednoho textu) jako
            // flash zprávu, zobrazí se po přesměrování zpět na detail.
        }

        redirect('/courses/' . $id);
        // Vždy se vrátí na detail kurzu - ať se přihlášení povedlo,
        // nebo ne (rozdíl je jen v tom, jaká flash zpráva se zobrazí).
    }

    private function notFound(): void
    {
        // Pomocná soukromá metoda - zobrazí stránku "404 nenalezeno"
        // se správným HTTP stavovým kódem.
        View::render('errors/404', ['title' => 'Nenalezeno'], 404);
    }
}
