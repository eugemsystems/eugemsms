<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-09 §4/BR-ACA-09-005. Rule-based assembly draws from the
 * question bank matching the configured difficulty mix and topic
 * coverage; a bank with insufficient items for the rule fails
 * assembly explicitly rather than silently repeating questions.
 */
class InsufficientQuestionBankException extends DomainException
{
    public static function forDifficulty(string $difficulty, int $required, int $available): self
    {
        return new self(
            "The question bank has only {$available} [{$difficulty}] item(s) matching the rule, but {$required} are required.",
            ['difficulty' => $difficulty, 'required' => $required, 'available' => $available],
        );
    }

    public function errorCode(): string
    {
        return 'INSUFFICIENT_QUESTION_BANK';
    }
}
