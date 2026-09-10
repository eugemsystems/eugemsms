<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-11-011/AC-CORE-11-005 — guardians before learners, learners
 * before balances, subjects before subject enrolments.
 */
class UnmetImportDependencyException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNMET_IMPORT_DEPENDENCY';
    }
}
