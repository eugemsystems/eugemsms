<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\DeleteAcademicYearData;
use Modules\Core\Domain\Events\Sessions\AcademicYearDeleted;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * ACT-DeleteAcademicYear (Book A CORE-03 §5, admin UI follow-up). A
 * genuine hard delete — `academic_years`/`terms` carry no soft-delete
 * column — permitted only when the year is unambiguously safe to remove,
 * following the same "throw a domain exception if unsafe" precedent as
 * `DeactivateCustomFieldAction`: never the *current* year (that would
 * leave the school with none), never a year whose own
 * academic_state/financial_state has moved past `planned`, and never a
 * year with any term that has itself moved past `planned` or is that
 * term's own `is_current`. `terms.academic_year_id` is `cascadeOnDelete`
 * at the DB level, so a year that passes every guard deletes its
 * (all-still-planned) terms along with it.
 */
final class DeleteAcademicYearAction extends Action
{
    public function execute(DeleteAcademicYearData $data): void
    {
        $year = AcademicYear::withoutGlobalScopes()
            ->where('id', $data->yearId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        if ($year->is_current) {
            throw new InvalidStateTransitionException(
                'The current academic year cannot be deleted — set a different year as current first.',
                ['academic_year_id' => $year->id],
            );
        }

        if ($year->academic_state !== PeriodState::Planned || $year->financial_state !== PeriodState::Planned) {
            throw new InvalidStateTransitionException(
                'This academic year has already moved past planned state and cannot be deleted.',
                ['academic_year_id' => $year->id],
            );
        }

        $hasUnsafeTerm = Term::withoutGlobalScopes()
            ->where('academic_year_id', $year->id)
            ->where(function ($query): void {
                $query->where('academic_state', '!=', PeriodState::Planned->value)
                    ->orWhere('financial_state', '!=', PeriodState::Planned->value)
                    ->orWhere('is_current', true);
            })
            ->exists();

        if ($hasUnsafeTerm) {
            throw new InvalidStateTransitionException(
                'This academic year has a term that is current or has moved past planned state, and cannot be deleted.',
                ['academic_year_id' => $year->id],
            );
        }

        $this->transaction(function () use ($year): void {
            event(new AcademicYearDeleted($year->id, $year->school_id));

            $year->delete();
        });
    }
}
