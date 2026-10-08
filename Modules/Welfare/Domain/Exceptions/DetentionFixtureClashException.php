<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book G BRD-07 §2/BR-BRD-07-011/AC-BRD-07-009. A detention scheduled
 * for a date the learner is already named in a sports fixture squad —
 * "the clash is flagged, and one is rescheduled before both take
 * effect": scheduling refuses outright, naming the fixture, rather
 * than silently double-booking the learner.
 */
class DetentionFixtureClashException extends DomainException
{
    public static function forFixture(int $studentId, int $fixtureId, string $opponent): self
    {
        return new self(
            "This learner is already named in the squad for the fixture against {$opponent} on this date — reschedule the detention or the fixture before either takes effect.",
            ['student_id' => $studentId, 'fixture_id' => $fixtureId],
        );
    }

    public function errorCode(): string
    {
        return 'DETENTION_FIXTURE_CLASH';
    }
}
