<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\AcademicYear;
use Modules\Intelligence\Models\EnrolmentForecast;
use Modules\People\Models\StudentEnrolment;

/**
 * ACT-GenerateEnrolmentForecast (Book J INT-03 §2/BR-INT-03-007
 * (AC-INT-03-006)). A trailing carry-forward from the most recently
 * COMPLETED academic year's own real `Modules\People\Models\StudentEnrolment`
 * history for this grade level — not a statistical model, and the
 * `basis_note` says so plainly. `confidence_band` is driven by how
 * many distinct terms of history exist, never hidden when thin.
 */
final class GenerateEnrolmentForecastAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(int $schoolId, int $targetAcademicYearId, int $gradeLevelId): EnrolmentForecast
    {
        $minTerms = (int) $this->settings->get('risk.forecast_minimum_terms', new ScopeChain(schoolId: $schoolId));

        $termsOfHistory = StudentEnrolment::where('school_id', $schoolId)
            ->where('grade_level_id', $gradeLevelId)
            ->distinct('term_id')->count('term_id');

        $mostRecentCompletedYear = AcademicYear::where('school_id', $schoolId)
            ->where('ends_on', '<', Carbon::today())
            ->orderByDesc('ends_on')
            ->first();

        $intake = null;
        $attrition = null;

        if ($mostRecentCompletedYear !== null) {
            $intake = StudentEnrolment::where('school_id', $schoolId)
                ->where('grade_level_id', $gradeLevelId)
                ->where('academic_year_id', $mostRecentCompletedYear->id)
                ->where('is_repeat', false)
                ->count();

            $attrition = StudentEnrolment::where('school_id', $schoolId)
                ->where('grade_level_id', $gradeLevelId)
                ->where('academic_year_id', $mostRecentCompletedYear->id)
                ->where('status', 'withdrawn')
                ->count();
        }

        $confidenceBand = match (true) {
            $termsOfHistory < $minTerms => 'low',
            $termsOfHistory < $minTerms * 2 => 'medium',
            default => 'high',
        };

        $basisNote = $mostRecentCompletedYear !== null
            ? "Projected intake/attrition is a trailing carry-forward of {$mostRecentCompletedYear->name}'s own actual enrolment for this grade ({$termsOfHistory} term(s) of history on file); a simple carry-forward, not a statistical model."
            : 'No completed academic year of enrolment history exists yet for this grade level; a projection cannot be produced.';

        return $this->transaction(fn (): EnrolmentForecast => EnrolmentForecast::updateOrCreate(
            ['school_id' => $schoolId, 'academic_year_id' => $targetAcademicYearId, 'grade_level_id' => $gradeLevelId],
            [
                'projected_intake' => $intake, 'projected_attrition' => $attrition,
                'confidence_band' => $confidenceBand, 'basis_note' => $basisNote, 'computed_at' => Carbon::now(),
            ],
        ));
    }
}
