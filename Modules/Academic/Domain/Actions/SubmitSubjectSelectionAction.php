<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\SubmitSubjectSelectionData;
use Modules\Academic\Domain\Events\SubjectSelectionSubmitted;
use Modules\Academic\Domain\Exceptions\SubjectSelectionBlockedException;
use Modules\Academic\Domain\Exceptions\SubjectSelectionRequiresAcknowledgementException;
use Modules\Academic\Domain\Support\SubjectSelectionRuleEngine;
use Modules\Academic\Models\Pathway;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;

/**
 * ACT-SubmitSubjectSelection (Book D ACA-02 §2/§6/BR-ACA-02-003).
 * Validates the proposed set through the same `SubjectSelectionRuleEngine`
 * `EnrolSubjectAction` uses — a submission and a direct add are held to
 * the same rules, just at a different point in the workflow. The
 * indicative fee is not computed here: the caller resolves it via
 * `PreviewIndicativeFeeAction` first and passes the figure in, keeping
 * this action free of any dependency on Finance (BR-ACA-02-016).
 */
final class SubmitSubjectSelectionAction extends Action
{
    public function __construct(private readonly SubjectSelectionRuleEngine $ruleEngine) {}

    public function execute(SubmitSubjectSelectionData $data): SubjectSelectionSubmission
    {
        $student = Student::findOrFail($data->studentId);

        $subjects = Subject::withoutGlobalScopes()->whereIn('id', $data->selectedSubjectIds)->get();
        $frameworkId = $subjects->first()?->framework_id;

        $pathwayCode = $data->pathwayId !== null
            ? Pathway::withoutGlobalScopes()->find($data->pathwayId)?->code
            : null;

        $result = $this->ruleEngine->validate(
            collect($data->selectedSubjectIds),
            $frameworkId ?? 0,
            $data->gradeLevelId,
            $pathwayCode,
            $student->school_id,
            $data->academicYearId,
            $student->id,
        );

        if (! $result->isValid) {
            throw SubjectSelectionBlockedException::forViolations($result->blocks);
        }

        if ($result->hasWarnings() && ! $data->acknowledgeWarnings) {
            throw SubjectSelectionRequiresAcknowledgementException::forViolations($result->warnings);
        }

        return $this->transaction(function () use ($student, $data, $result): SubjectSelectionSubmission {
            $submission = SubjectSelectionSubmission::create([
                'school_id' => $student->school_id,
                'academic_year_id' => $data->academicYearId,
                'student_id' => $student->id,
                'grade_level_id' => $data->gradeLevelId,
                'pathway_id' => $data->pathwayId,
                'selected_subject_ids' => $data->selectedSubjectIds,
                'reserve_subject_ids' => $data->reserveSubjectIds,
                'validation_result' => [
                    'warnings' => $result->warnings->pluck('message')->all(),
                    'blocks' => [],
                ],
                'indicative_fee_minor' => $data->indicativeFeeMinor,
                'indicative_fee_currency' => $data->indicativeFeeCurrency,
                'status' => 'submitted',
                'submitted_by' => $data->submittedByUserId,
                'submitted_at' => Carbon::now(),
            ]);

            event(new SubjectSelectionSubmitted($submission));

            return $submission;
        });
    }
}
