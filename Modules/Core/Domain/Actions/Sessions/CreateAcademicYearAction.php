<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Domain\DataObjects\Sessions\CreateYearData;
use Modules\Core\Domain\Events\Sessions\AcademicYearCreated;
use Modules\Core\Models\AcademicYear;

/**
 * ACT-CreateAcademicYear (Book A CORE-03 §4). BR-CORE-03-001: a school
 * has exactly one `is_current = 1` academic year at any moment — enforced
 * here inside the transaction, not left to a partial unique index (MySQL
 * has no native partial-index support). BR-CORE-03-003: `terms_per_year`
 * defaults to 3; when `generateThreeTerms` is set, this delegates to
 * `CreateTermAction` for each one rather than duplicating its guards.
 */
final class CreateAcademicYearAction extends Action
{
    public function __construct(
        private readonly CreateTermAction $createTerm,
    ) {}

    public function execute(CreateYearData $data): AcademicYear
    {
        Validator::make(
            ['name' => $data->name, 'school_id' => $data->schoolId],
            [
                'name' => ['required', 'string', 'max:30', 'unique:academic_years,name,NULL,id,school_id,'.$data->schoolId],
                'school_id' => ['required', 'integer', 'exists:schools,id'],
            ],
        )->validate();

        if ($data->endsOn->lessThanOrEqualTo($data->startsOn)) {
            throw ValidationException::withMessages(['ends_on' => 'The end date must be after the start date.']);
        }

        return $this->transaction(function () use ($data): AcademicYear {
            $year = new AcademicYear([
                'school_id' => $data->schoolId,
                'name' => $data->name,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'is_current' => false,
                'academic_state' => 'planned',
                'financial_state' => 'planned',
            ]);
            $year->created_by = $data->actingUserId;
            $year->updated_by = $data->actingUserId;
            $year->save();

            if ($data->generateThreeTerms) {
                $this->generateThreeTerms($year, $data);
            }

            event(new AcademicYearCreated($year));

            return $year;
        });
    }

    private function generateThreeTerms(AcademicYear $year, CreateYearData $data): void
    {
        $totalDays = (int) $data->startsOn->diffInDays($data->endsOn);
        $segment = intdiv($totalDays, 3);

        $term1Start = $data->startsOn->copy();
        $term1End = $term1Start->copy()->addDays($segment);
        $term2Start = $term1End->copy()->addDay();
        $term2End = $term2Start->copy()->addDays($segment);
        $term3Start = $term2End->copy()->addDay();
        $term3End = $data->endsOn->copy();

        foreach ([
            [1, 'Term 1', $term1Start, $term1End],
            [2, 'Term 2', $term2Start, $term2End],
            [3, 'Term 3', $term3Start, $term3End],
        ] as [$number, $name, $startsOn, $endsOn]) {
            $this->createTerm->execute(new CreateTermData(
                schoolId: $data->schoolId,
                academicYearId: $year->id,
                number: $number,
                name: $name,
                startsOn: $startsOn,
                endsOn: $endsOn,
                actingUserId: $data->actingUserId,
            ));
        }
    }
}
