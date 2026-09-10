<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\Violation;
use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book D ACA-01 §3/BR-ACA-01-008. Raised when a proposed subject set
 * fails at least one `block`-severity `subject_selection_rules` row —
 * there is no acknowledgement path around a block (AC-ACA-01-002).
 */
class SubjectSelectionBlockedException extends DomainException
{
    /**
     * @param  Collection<int, Violation>  $blocks
     */
    public static function forViolations(Collection $blocks): self
    {
        $messages = $blocks->pluck('message')->implode(' ');

        return new self($messages, ['blocks' => $blocks->pluck('message')->all()]);
    }

    public function errorCode(): string
    {
        return 'SUBJECT_SELECTION_BLOCKED';
    }
}
