<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\Violation;
use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-01 §3/BR-ACA-01-008. A `warn`-severity rule failed and the
 * caller did not set `acknowledgeWarnings` — the change is refused
 * until it is retried with that acknowledgement, which is then
 * recorded against the acknowledging user (AC-ACA-01-002B).
 */
class SubjectSelectionRequiresAcknowledgementException extends DomainException
{
    /**
     * @param  Collection<int, Violation>  $warnings
     */
    public static function forViolations(Collection $warnings): self
    {
        $messages = $warnings->pluck('message')->implode(' ');

        return new self($messages, ['warnings' => $warnings->pluck('message')->all()]);
    }

    public function errorCode(): string
    {
        return 'SUBJECT_SELECTION_REQUIRES_ACKNOWLEDGEMENT';
    }
}
