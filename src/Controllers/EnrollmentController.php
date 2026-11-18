<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EnrollmentRepository;
use App\Validation\ValidationException;
use PDO;

/**
 * Změny stavu přihlášky: enrolled -> cancelled, enrolled -> completed.
 */
final class EnrollmentController
{
    private EnrollmentRepository $enrollments;

    public function __construct(PDO $pdo)
    {
        $this->enrollments = new EnrollmentRepository($pdo);
    }

    public function cancel(string $id): void
    {
        $row = $this->enrollments->find((int) $id);
        $courseId = $row['course_id'] ?? null;

        try {
            $this->enrollments->cancel((int) $id);
            flash('success', 'Přihláška byla zrušena.');
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors()));
        }

        redirect('/courses/' . ($courseId ?? ''));
    }

    public function complete(string $id): void
    {
        $row = $this->enrollments->find((int) $id);
        $courseId = $row['course_id'] ?? null;

        try {
            $this->enrollments->complete((int) $id);
            flash('success', 'Přihláška byla označena jako absolvovaná.');
        } catch (ValidationException $e) {
            flash('error', implode(' ', $e->errors()));
        }

        redirect('/courses/' . ($courseId ?? ''));
    }
}
