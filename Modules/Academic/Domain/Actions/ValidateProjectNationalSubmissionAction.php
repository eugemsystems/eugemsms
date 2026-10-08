<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\ProjectSubmissionCandidateIssue;
use Modules\Academic\Domain\DataObjects\ValidateProjectNationalSubmissionData;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ValidateProjectNationalSubmission (Book E ACA-06 §6/BR-ACA-06-018
 * — "with a validation report run before export"). Checks the
 * candidate set for the active instrument's briefs in one academic
 * year: every non-exempt learner project must be `verified`
 * (BR-ACA-06-014 — only a verified outcome counts; `outcomeFor()` on
 * `EloquentContinuousAssessmentProvider` derives "outcome" from this
 * same `status` column — `learner_projects.outcome` itself is never
 * written anywhere in the domain layer, a dead column, not the real
 * signal) with a recorded mark and grade, since nothing below that
 * status is fit to submit. An `exempt` project is silently excluded
 * from the set, not flagged as an error (BR-ACA-06-016's own carve-out
 * for exemption). `error` severity blocks export
 * (`GenerateProjectNationalSubmissionExportAction` refuses while any
 * exist); `warning` only flags. Mirrors Book H3 CMP-01's own
 * `ValidateZimsecCandidatesAction` shape — a per-candidate issue list,
 * not a pass/fail flag — without duplicating its ZIMSEC-specific
 * configurable rule table, which this export has no equivalent of.
 */
final class ValidateProjectNationalSubmissionAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, ProjectSubmissionCandidateIssue>
     */
    public function execute(ValidateProjectNationalSubmissionData $data): Collection
    {
        AssessmentInstrument::findOrFail($data->instrumentId);

        $briefIds = ProjectBrief::where('instrument_id', $data->instrumentId)
            ->where('academic_year_id', $data->academicYearId)
            ->pluck('id');

        $projects = LearnerProject::whereIn('brief_id', $briefIds)
            ->with('student')
            ->get();

        $issues = collect();

        foreach ($projects as $project) {
            if ($project->status === 'exempt') {
                continue;
            }

            $student = $project->student;
            $admissionNumber = $student === null ? '' : (string) $student->admission_number;
            $studentName = $student === null ? "Student #{$project->student_id}" : $student->fullName();

            $issue = fn (string $field, string $severity, string $message): ProjectSubmissionCandidateIssue => new ProjectSubmissionCandidateIssue(
                learnerProjectId: $project->id, studentName: $studentName, admissionNumber: $admissionNumber,
                field: $field, severity: $severity, message: $message,
            );

            if ($project->status !== 'verified') {
                $issues->push($issue('status', 'error', "Project is {$project->status}, not verified — not fit for national submission."));

                continue;
            }

            if ($project->raw_mark === null || $project->percent === null) {
                $issues->push($issue('raw_mark', 'error', 'A verified project has no recorded mark.'));
            }

            if ($project->grade === null) {
                $issues->push($issue('grade', 'warning', 'A verified project has no recorded grade.'));
            }

            if ($admissionNumber === '') {
                $issues->push($issue('admission_number', 'error', 'The learner has no admission number to submit against.'));
            }
        }

        return $issues->values();
    }
}
