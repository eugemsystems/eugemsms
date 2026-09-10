<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-02 §3 ⭐/BR-BRD-02-010/AC-BRD-02-004. "Acknowledged" is
 * not an action — a step flagged `requires_action_record` cannot be
 * satisfied without free text describing what was actually checked.
 */
class ActionRecordRequiredException extends DomainException
{
    public static function forStep(int $incidentId, int $stepNumber): self
    {
        return new self(
            "Step {$stepNumber} of incident #{$incidentId} requires a free-text action record — acknowledgement alone does not satisfy it.",
            ['incident_id' => $incidentId, 'step_number' => $stepNumber],
        );
    }

    public function errorCode(): string
    {
        return 'ACTION_RECORD_REQUIRED';
    }
}
