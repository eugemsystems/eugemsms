<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\DeleteTermData;
use Modules\Core\Domain\Events\Sessions\TermDeleted;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Models\Term;

/**
 * ACT-DeleteTerm (Book A CORE-03 §5, admin UI follow-up). A genuine hard
 * delete, permitted only when safe — same precedent as
 * `DeleteAcademicYearAction`/`DeactivateCustomFieldAction`: never the
 * *current* term, never a term whose own academic_state/financial_state
 * has moved past `planned`.
 */
final class DeleteTermAction extends Action
{
    public function execute(DeleteTermData $data): void
    {
        $term = Term::withoutGlobalScopes()
            ->where('id', $data->termId)
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->firstOrFail();

        if ($term->is_current) {
            throw new InvalidStateTransitionException(
                'The current term cannot be deleted — set a different term as current first.',
                ['term_id' => $term->id],
            );
        }

        if ($term->academic_state !== PeriodState::Planned || $term->financial_state !== PeriodState::Planned) {
            throw new InvalidStateTransitionException(
                'This term has already moved past planned state and cannot be deleted.',
                ['term_id' => $term->id],
            );
        }

        $this->transaction(function () use ($term): void {
            event(new TermDeleted($term->id, $term->school_id, $term->academic_year_id));

            $term->delete();
        });
    }
}
