<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\UpdateTermData;
use Modules\Core\Domain\Events\Sessions\TermUpdated;
use Modules\Core\Models\Term;

/**
 * ACT-UpdateTerm (Book A CORE-03 §5, admin UI follow-up). Mirrors
 * `ACT-CreateTerm`'s guards (BR-CORE-03-002: terms within a year may not
 * overlap) with the term itself excluded from both the uniqueness and
 * overlap checks. `is_current`/`academic_state`/`financial_state` are
 * untouched — those change only through their own dedicated gateways.
 */
final class UpdateTermAction extends Action
{
    public function execute(UpdateTermData $data): Term
    {
        // Not Term::query()->findOrFail(): see UpdateAcademicYearAction's
        // identical note — this operates on an explicit id triple, not
        // whatever ambient SchoolContext happens to be set.
        $term = Term::withoutGlobalScopes()
            ->where('id', $data->termId)
            ->where('school_id', $data->schoolId)
            ->where('academic_year_id', $data->academicYearId)
            ->firstOrFail();

        Validator::make(
            ['number' => $data->number, 'name' => $data->name],
            [
                'number' => [
                    'required', 'integer', 'min:1', 'max:127',
                    'unique:terms,number,'.$data->termId.',id,school_id,'.$data->schoolId.',academic_year_id,'.$data->academicYearId,
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
            ->where('id', '!=', $term->id)
            ->where('starts_on', '<=', $data->endsOn)
            ->where('ends_on', '>=', $data->startsOn)
            ->exists();

        if ($overlapping) {
            throw ValidationException::withMessages(['starts_on' => 'This term overlaps another term in the same academic year.']);
        }

        return $this->transaction(function () use ($term, $data): Term {
            $term->fill([
                'number' => $data->number,
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
            ]);
            $term->updated_by = $data->actingUserId;
            $term->save();

            event(new TermUpdated($term));

            return $term;
        });
    }
}
