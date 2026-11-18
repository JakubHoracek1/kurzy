<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ParticipantRepository;
use App\Validation\ValidationException;
use App\View;
use PDO;

/**
 * CRUD účastníků.
 */
final class ParticipantController
{
    private ParticipantRepository $participants;

    public function __construct(PDO $pdo)
    {
        $this->participants = new ParticipantRepository($pdo);
    }

    public function index(): void
    {
        View::render('participants/index', [
            'title' => 'Účastníci',
            'participants' => $this->participants->all(),
        ]);
    }

    public function create(): void
    {
        View::render('participants/form', [
            'title' => 'Nový účastník',
            'participant' => null,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(): void
    {
        try {
            $this->participants->create($_POST);
            flash('success', 'Účastník byl vytvořen.');
            redirect('/participants');
        } catch (ValidationException $e) {
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
        $participant = $this->participants->find((int) $id);
        if ($participant === null) {
            $this->notFound();
            return;
        }

        View::render('participants/form', [
            'title' => 'Upravit účastníka',
            'participant' => $participant,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function update(string $id): void
    {
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
        $this->participants->delete((int) $id);
        flash('success', 'Účastník byl smazán.');
        redirect('/participants');
    }

    private function notFound(): void
    {
        View::render('errors/404', ['title' => 'Nenalezeno'], 404);
    }
}
