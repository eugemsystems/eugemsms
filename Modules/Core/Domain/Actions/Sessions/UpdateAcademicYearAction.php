<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\UpdateAcademicYearData;
use Modules\Core\Domain\Events\Sessions\AcademicYearUpdated;
use Modules\Core\Models\AcademicYear;

/**
 * ACT-UpdateAcademicYear (Book A CORE-03 §5, admin UI follow-up). Edits
 * name/dates and is the only place `is_current` is ever flipped to `true`
 * outside a factory/seeder/installer — BR-CORE-03-001: a school has
 * exactly one `is_current = 1` academic year at any moment, enforced here
 * inside the transaction by clearing any other current year for the same
 * school before setting this one. `academic_state`/`financial_state` are
 * untouched — those change only through `ACT-TransitionPeriodState`
 * (`GuardsPeriodStateWrites`).
 */
final class UpdateAcademicYearAction extends Action
{
    public function execute(UpdateAcademicYearData $data): AcademicYear
    {
        // Not AcademicYear::query()->findOrFail(): that carries
        // BelongsToSchool's global scope, filtered by the *ambient*
        // SchoolContext — this action operates on an explicit
        // schoolId/yearId pair that has no reason to match whatever
        // context (if any) happens to be set when it's called.
        $year = AcademicYear::withoutGlobalScopes()
            ->where('id', $data->yearId)
            ->where('school_id', $data->schoolId)
            ->firstOrFail();

        Validator::make(
            ['name' => $data->name],
            [
                'name' => ['required', 'string', 'max:30', 'unique:academic_years,name,'.$data->yearId.',id,school_id,'.$data->schoolId],
            ],
        )->validate();

        if ($data->endsOn->lessThanOrEqualTo($data->startsOn)) {
            throw ValidationException::withMessages(['ends_on' => 'The end date must be after the start date.']);
        }

        return $this->transaction(function () use ($year, $data): AcademicYear {
            if ($data->isCurrent && ! $year->is_current) {
                AcademicYear::withoutGlobalScopes()
                    ->where('school_id', $data->schoolId)
                    ->where('id', '!=', $year->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $year->fill([
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'is_current' => $data->isCurrent,
            ]);
            $year->updated_by = $data->actingUserId;
            $year->save();

            event(new AcademicYearUpdated($year));

            return $year;
        });
    }
}
