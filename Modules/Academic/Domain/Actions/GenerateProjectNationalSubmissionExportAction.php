<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\GenerateProjectNationalSubmissionData;
use Modules\Academic\Domain\DataObjects\ValidateProjectNationalSubmissionData;
use Modules\Academic\Domain\Exceptions\ProjectSubmissionExportBlockedException;
use Modules\Academic\Domain\Support\ReportCardTemplate;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectNationalSubmission;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * ACT-GenerateProjectNationalSubmissionExport (Book E ACA-06 §6/
 * BR-ACA-06-018). "The format required by the Ministry... is a
 * configurable template, not code" — rendered through the same
 * `DocumentTemplate`/`GenerateDocumentAction` mechanism
 * `GenerateTranscriptAction`/`CompileProjectPortfolioAction` already
 * use, lazily creating a school's own default template on first export
 * rather than hard-coding a Ministry layout nowhere specified. Refuses
 * outright while `ValidateProjectNationalSubmissionAction` still finds
 * an `error`-severity issue in the candidate set — the validation
 * report the rule names must be clean first.
 */
final class GenerateProjectNationalSubmissionExportAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ValidateProjectNationalSubmissionAction $validate,
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
    ) {}

    public function execute(GenerateProjectNationalSubmissionData $data): ProjectNationalSubmission
    {
        $instrument = AssessmentInstrument::findOrFail($data->instrumentId);
        $year = AcademicYear::findOrFail($data->academicYearId);

        $issues = $this->validate->execute(new ValidateProjectNationalSubmissionData(
            instrumentId: $data->instrumentId, academicYearId: $data->academicYearId,
        ));
        $errorCount = $issues->where('severity', 'error')->count();

        if ($errorCount > 0) {
            throw ProjectSubmissionExportBlockedException::hasErrors($data->instrumentId, $data->academicYearId, $errorCount);
        }

        $briefIds = ProjectBrief::where('instrument_id', $instrument->id)
            ->where('academic_year_id', $year->id)
            ->pluck('id');

        $projects = LearnerProject::whereIn('brief_id', $briefIds)
            ->where('status', 'verified')
            ->with(['student', 'subject', 'brief'])
            ->get();

        $candidates = $projects->map(fn (LearnerProject $project): array => [
            'admission_number' => $project->student === null ? '' : (string) $project->student->admission_number,
            'student_name' => $project->student === null ? '' : $project->student->fullName(),
            'subject' => $project->subject === null ? '' : $project->subject->name,
            'project_title' => $project->brief === null ? '' : $project->brief->title,
            'raw_mark' => (string) ($project->raw_mark ?? ''),
            'percent' => (string) ($project->percent ?? ''),
            'grade' => (string) ($project->grade ?? ''),
        ])->values()->all();

        ReportCardTemplate::registerVariables();

        $document = $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $instrument->school_id,
            documentType: ReportCardTemplate::PROJECT_NATIONAL_SUBMISSION_TYPE,
            data: [
                'school' => ['name' => (string) School::findOrFail($instrument->school_id)->name],
                'instrument' => ['code' => $instrument->code, 'name' => $instrument->name],
                'year' => ['name' => $year->name],
                'candidates' => $candidates,
                'generated_on' => Carbon::today()->toDateString(),
            ],
            generatedByUserId: $data->generatedByUserId,
            templateId: $this->template($instrument->school_id, $data->generatedByUserId)->id,
            documentableType: $instrument->getMorphClass(),
            documentableId: $instrument->id,
            allocateNumber: false,
            verifiable: true,
        ));

        return ProjectNationalSubmission::create([
            'school_id' => $instrument->school_id,
            'instrument_id' => $instrument->id,
            'academic_year_id' => $year->id,
            'document_id' => $document->id,
            'candidate_count' => count($candidates),
            'exported_by' => $data->generatedByUserId,
            'exported_at' => Carbon::now(),
        ]);
    }

    private function template(int $schoolId, int $userId): DocumentTemplate
    {
        return DocumentTemplate::query()->where('school_id', $schoolId)->where('template_type', ReportCardTemplate::PROJECT_NATIONAL_SUBMISSION_TYPE)->where('is_active', true)->where('is_default', true)->first()
            ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
                schoolId: $schoolId, templateType: ReportCardTemplate::PROJECT_NATIONAL_SUBMISSION_TYPE, name: 'Standard SBP national submission',
                content: ReportCardTemplate::nationalSubmissionContent(), isDefault: true, createdByUserId: $userId,
            ));
    }
}
