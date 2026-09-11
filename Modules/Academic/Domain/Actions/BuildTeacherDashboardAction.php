<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\BuildTeacherDashboardData;
use Modules\Academic\Domain\DataObjects\TeacherDashboardSummary;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\LessonPlan;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;

/**
 * ACT-BuildTeacherDashboard (Book K ACA-11 §4/BR-ACA-11-009). Reads
 * from each owning module rather than maintaining a second copy of
 * anything: coverage from this module's own `SyllabusCoverageRecord`,
 * lesson plan submission from this module's own `LessonPlan`,
 * observation ratings from this module's own `LessonObservation`.
 * `ACA-04` marking-compliance and `ACA-05` results outcomes are the
 * spec's own "where enabled" extension points — not wired in this
 * pass, so this dashboard is coverage/planning/observation only; a
 * caller wanting the fuller picture combines this with those modules'
 * own read paths.
 *
 * `lessonPlanSubmissionRate` is an approximation: this table has no
 * `submitted_at` timestamp, so "timeliness" here means "was this
 * already-due lesson plan ever submitted", not "was it submitted
 * before its own lesson date".
 */
final class BuildTeacherDashboardAction extends Action
{
    protected bool $transactional = false;

    public function execute(BuildTeacherDashboardData $data): TeacherDashboardSummary
    {
        $schemeIds = SchemeOfWork::query()
            ->where('teacher_staff_id', $data->teacherStaffId)
            ->where('term_id', $data->termId)
            ->pluck('id');

        $coverageRecords = SyllabusCoverageRecord::query()->whereIn('scheme_of_work_id', $schemeIds)->get();
        $coveragePercent = $coverageRecords->isEmpty()
            ? 0.0
            : round(($coverageRecords->whereNotNull('actual_delivered_on')->count() / $coverageRecords->count()) * 100, 2);

        $term = Term::findOrFail($data->termId);

        $duePlans = LessonPlan::query()
            ->where('teacher_staff_id', $data->teacherStaffId)
            ->whereBetween('lesson_date', [$term->starts_on->toDateString(), $term->ends_on->toDateString()])
            ->where('lesson_date', '<=', Carbon::today()->toDateString())
            ->get();

        $submissionRate = $duePlans->isEmpty()
            ? 0.0
            : round(($duePlans->whereIn('status', ['submitted', 'reviewed'])->count() / $duePlans->count()) * 100, 2);

        $observations = LessonObservation::query()
            ->where('observed_staff_id', $data->teacherStaffId)
            ->where('term_id', $data->termId)
            ->get();

        $ratingCounts = $observations
            ->whereNotNull('overall_rating')
            ->countBy(fn (LessonObservation $o): string => (string) $o->overall_rating)
            ->all();

        return new TeacherDashboardSummary(
            coveragePercent: $coveragePercent,
            lessonPlanSubmissionRate: $submissionRate,
            observationRatingCounts: $ratingCounts,
            lessonPlanCount: $duePlans->count(),
            observationCount: $observations->count(),
        );
    }
}
