<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\EnrollmentRepository;
use App\Repositories\ParticipantRepository;
use App\Validation\ValidationException;
use App\View;
use PDO;

/**
 * CRUD kurzů, zobrazení obsazenosti a seznamu přihlášených,
 * filtr podle termínu a volných míst, přihlašování účastníků.
 */
final class CourseController
{
    private CourseRepository $courses;
    private EnrollmentRepository $enrollments;
    private ParticipantRepository $participants;

    public function __construct(PDO $pdo)
    {
        $this->courses = new CourseRepository($pdo);
        $this->enrollments = new EnrollmentRepository($pdo);
        $this->participants = new ParticipantRepository($pdo);
    }

    /** Seznam kurzů + filtr podle termínu a volných míst. */
    public function index(): void
    {
        $from = trim((string) ($_GET['from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? ''));
        $onlyFree = !empty($_GET['only_free']);

        // Filtr "do" zadává uživatel jako datum (bez času) - aby zahrnoval
        // i kurzy konající se v daný den, porovnáváme do konce dne.
        $toForQuery = ($to !== '' && !str_contains($to, 'T')) ? $to . 'T23:59:59' : $to;

        $courses = $this->courses->all([
            'from' => $from,
            'to' => $toForQuery,
            'only_free' => $onlyFree,
        ]);

        View::render('courses/index', [
            'title' => 'Kurzy',
            'courses' => $courses,
            'filters' => ['from' => $from, 'to' => $to, 'only_free' => $onlyFree],
        ]);
    }

    /** Formulář pro nový kurz. */
    public function create(): void
    {
        View::render('courses/form', [
            'title' => 'Nový kurz',
            'course' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    /** Uložení nového kurzu. */
    public function store(): void
    {
        try {
            $this->courses->create($_POST);
            flash('success', 'Kurz byl vytvořen.');
            redirect('/courses');
        } catch (ValidationException $e) {
            View::render('courses/form', [
                'title' => 'Nový kurz',
                'course' => null,
                'errors' => $e->errors(),
                'old' => $_POST,
            ], 422);
        }
    }

    /** Formulář pro úpravu kurzu. */
    public function edit(string $id): void
    {
        $course = $this->courses->find((int) $id);
        if ($course === null) {
            $this->notFound();
            return;
        }

        View::render('courses/form', [
            'title' => 'Upravit kurz',
            'course' => $course,
            'errors' => [],
            'old' => [],
        ]);
    }

    /** Uložení úpravy kurzu. */
    public function update(string $id): void
    {
        $course = $this->courses->find((int) $id);
        if ($course === null) {
            $this->notFound();
            return;
        }

        try {
            $this->courses->update((int) $id, $_POST);
            flash('success', 'Kurz byl upraven.');
            redirect('/courses');
        } catch (ValidationException $e) {
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
        $this->courses->delete((int) $id);
        flash('success', 'Kurz byl smazán.');
        redirect('/courses');
    }

    /** Detail kurzu: obsazenost, seznam přihlášených, formulář pro přihlášení. */
    public function show(string $id): void
    {
        $course = $this->courses->find((int) $id);
        if ($course === null) {
            $this->notFound();
            return;
        }

        $enrollments = $this->enrollments->listByCourse((int) $id);
        $availableParticipants = $this->participants->allNotActivelyEnrolledInCourse((int) $id);

        View::render('courses/show', [
            'title' => 'Kurz: ' . $course['name'],
            'course' => $course,
            'enrollments' => $enrollments,
            'availableParticipants' => $availableParticipants,
        ]);
    }

    /** Přihlášení účastníka do kurzu (hlídá duplicitu a kapacitu). */
    public function enroll(string $id): void
    {
        $participantId = (int) ($_POST['participant_id'] ?? 0);

        try {
            $this->enrollments->enroll((int) $id, $participantId);
            flash('success', 'Účastník byl přihlášen.');
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors()));
        }

        redirect('/courses/' . $id);
    }

    private function notFound(): void
    {
        View::render('errors/404', ['title' => 'Nenalezeno'], 404);
    }
}
