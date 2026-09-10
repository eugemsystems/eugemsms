<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Domain\Events\Sessions\TermCreated;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * ACT-CreateTerm (Book A CORE-03 §4). BR-CORE-03-002: terms within a year
 * may not overlap — validated here on create. `teaching_days` is left
 * null; `ACT-GenerateTermWeeks` computes and writes it once weeks exist
 * (BR-CORE-03-004 — the figure is a function of the generated weeks and
 * calendar holidays, not something this action can know in isolation).
 */
final class CreateTermAction extends Action
{
    public function execute(CreateTermData $data): Term
    {
        // Not AcademicYear::query()->findOrFail(): that carries
        // BelongsToSchool's global scope, filtered by the *ambient*
        // SchoolContext — this action operates on an explicit
        // schoolId/academicYearId pair that has no reason to match
        // whatever context (if any) happens to be set when it's called.
        AcademicYear::withoutGlobalScopes()
            ->where('id', $data->academicYearId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            [
                'academic_year_id' => $data->academicYearId,
                'number' => $data->number,
                'name' => $data->name,
            ],
            [
                'academic_year_id' => ['required', 'integer', 'exists:academic_years,id,school_id,'.$data->schoolId],
                'number' => [
                    'required', 'integer', 'min:1', 'max:127',
                    'unique:terms,number,NULL,id,school_id,'.$data->schoolId.',academic_year_id,'.$data->academicYearId,
                ],
                'name' => ['required', 'string', 'max:40'],
            ],
        )->validate();

        if ($data->endsOn->lessThanOrEqualTo($data->startsOn)) {
            throw ValidationException::withMessages(['ends_on' => 'The end date must be after the start date.']);
        }

        $overlapping = Term::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('starts_on', '<=', $data->endsOn)
            ->where('ends_on', '>=', $data->startsOn)
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages(['starts_on' => 'This term overlaps another term in the same academic year.']);
        }

        return $this->transaction(function () use ($data): Term {
            $term = new Term([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'number' => $data->number,
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'half_term_starts_on' => $data->halfTermStartsOn,
                'half_term_ends_on' => $data->halfTermEndsOn,
                'fee_due_on' => $data->feeDueOn,
                'results_due_on' => $data->resultsDueOn,
                'reports_release_on' => $data->reportsReleaseOn,
                'is_current' => false,
                'academic_state' => 'planned',
                'financial_state' => 'planned',
            ]);
            $term->created_by = $data->actingUserId;
            $term->updated_by = $data->actingUserId;
            $term->save();

            event(new TermCreated($term));

            return $term;
        });
    }
}
