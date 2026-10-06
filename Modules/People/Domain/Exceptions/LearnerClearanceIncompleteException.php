<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-01 §7/BR-PPL-01-014. A learner leaving the school still has
 * property or fees outstanding; every failure is listed.
 */
class LearnerClearanceIncompleteException extends DomainException
{
    /**
     * @param  array<int, string>  $reasons
     */
    public static function forLearner(int $studentId, array $reasons): self
    {
        return new self('Clearance is incomplete: '.implode('; ', $reasons).'.', ['student_id' => $studentId, 'reasons' => $reasons]);
    }

    public function errorCode(): string
    {
        return 'LEARNER_CLEARANCE_INCOMPLETE';
    }
}
