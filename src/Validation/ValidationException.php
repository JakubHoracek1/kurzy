<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Výjimka nesoucí seznam chyb validace (validační pravidla ze zadání).
 */
final class ValidationException extends \RuntimeException
{
    /** @var string[] */
    private array $errors;

    /**
     * @param string[] $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct(implode(' ', $errors));
    }

    /**
     * @return string[]
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
