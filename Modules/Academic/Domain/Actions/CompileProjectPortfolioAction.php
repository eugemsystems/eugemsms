<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\CompileProjectPortfolioData;
use Modules\Academic\Domain\Events\PortfolioCompiled;
use Modules\Academic\Domain\Support\ReportCardTemplate;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectEvidence;
use Modules\Academic\Models\ProjectMilestone;
use Modules\Academic\Models\ProjectPortfolio;
use Modules\Academic\Models\ProjectRubric;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\File;
use Modules\Core\Models\School;

/**
 * ACT-CompileProjectPortfolio (Book E ACA-06 §7/§9, BR-ACA-06-017).
 * Pulls together one learner's brief, every milestone, all evidence,
 * the rubric breakdown, and the marker/moderator comments into a
 * single reviewable document — for HOD sign-off, a Ministry visit, or
 * a parent request. Generated from source every time, like
 * `GenerateTranscriptAction`, so a portfolio compiled today matches
 * one compiled next year for the same project.
 */
final class CompileProjectPortfolioAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
    ) {}

    public function execute(CompileProjectPortfolioData $data): ProjectPortfolio
    {
        $learnerProject = LearnerProject::with(['student', 'subject'])->findOrFail($data->learnerProjectId);
        $brief = ProjectBrief::findOrFail($learnerProject->brief_id);
        $rubric = ProjectRubric::with('criteria')->findOrFail($brief->rubric_id);

        $milestones = ProjectMilestone::query()->where('brief_id', $brief->id)->orderBy('sequence')->get();
        $submissions = $learnerProject->milestoneSubmissions()->get()->keyBy('milestone_id');

        $milestoneRows = $milestones->map(function (ProjectMilestone $milestone) use ($submissions): array {
            $submission = $submissions->get($milestone->id);

            return [
                'title' => $milestone->title,
                'due_on' => $milestone->due_on->toDateString(),
                'status' => $submission === null ? 'pending' : $submission->status,
                'mark' => $submission?->mark,
                'feedback' => $submission === null ? '' : (string) ($submission->feedback ?? ''),
            ];
        })->values()->all();

        $evidenceFileNames = File::query()
            ->whereIn('id', $learnerProject->evidence()->whereNotNull('file_id')->pluck('file_id'))
            ->pluck('original_name', 'id');

        $evidenceRows = $learnerProject->evidence()->orderBy('uploaded_at')->get()->map(fn (ProjectEvidence $evidence): array => [
            'type' => $evidence->evidence_type,
            'caption' => (string) ($evidence->caption ?? ''),
            'uploaded_at' => $evidence->uploaded_at->toDateString(),
            'is_final_submission' => $evidence->is_final_submission,
            'reference' => $evidence->file_id !== null
                ? (string) ($evidenceFileNames[$evidence->file_id] ?? 'Attached file')
                : (string) ($evidence->external_url ?? ''),
        ])->values()->all();

        $criterionMarks = $learnerProject->criterion_marks ?? [];
        $rubricRows = $rubric->criteria->map(fn ($criterion): array => [
            'criterion' => $criterion->criterion,
            'max_mark' => $criterion->max_mark,
            'weight_percent' => $criterion->weight_percent,
            'awarded' => $criterionMarks[$criterion->criterion] ?? null,
        ])->values()->all();

        ReportCardTemplate::registerVariables();

        $document = $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $learnerProject->school_id,
            documentType: ReportCardTemplate::PROJECT_PORTFOLIO_TYPE,
            data: [
                'school' => ['name' => (string) School::findOrFail($learnerProject->school_id)->name],
                'student' => ['name' => $learnerProject->student->fullName(), 'admission_number' => $learnerProject->student->admission_number],
                'subject' => ['name' => (string) $learnerProject->subject?->name],
                'brief' => [
                    'title' => $brief->title,
                    'description' => $brief->description,
                    'deliverables' => $brief->deliverables,
                    'starts_on' => $brief->starts_on->toDateString(),
                    'due_on' => $brief->due_on->toDateString(),
                    'max_mark' => $brief->max_mark,
                ],
                'milestones' => $milestoneRows,
                'evidence' => $evidenceRows,
                'rubric' => $rubricRows,
                'raw_mark' => $learnerProject->raw_mark,
                'percent' => $learnerProject->percent,
                'grade' => $learnerProject->grade,
                'marker_comment' => (string) ($learnerProject->marker_comment ?? ''),
                'moderation_note' => (string) ($learnerProject->moderation_note ?? ''),
                'compiled_on' => Carbon::today()->toDateString(),
            ],
            generatedByUserId: $data->compiledByUserId,
            templateId: $this->template($learnerProject->school_id, $data->compiledByUserId)->id,
            documentableType: $learnerProject->getMorphClass(),
            documentableId: $learnerProject->id,
            allocateNumber: false,
            verifiable: true,
        ));

        $portfolio = ProjectPortfolio::create([
            'school_id' => $learnerProject->school_id,
            'learner_project_id' => $learnerProject->id,
            'document_id' => $document->id,
            'compiled_by' => $data->compiledByUserId,
            'compiled_at' => Carbon::now(),
        ]);

        event(new PortfolioCompiled($portfolio));

        return $portfolio;
    }

    private function template(int $schoolId, int $userId): DocumentTemplate
    {
        return DocumentTemplate::query()->where('school_id', $schoolId)->where('template_type', ReportCardTemplate::PROJECT_PORTFOLIO_TYPE)->where('is_active', true)->where('is_default', true)->first()
            ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
                schoolId: $schoolId, templateType: ReportCardTemplate::PROJECT_PORTFOLIO_TYPE, name: 'Standard project portfolio',
                content: ReportCardTemplate::portfolioContent(), isDefault: true, createdByUserId: $userId,
            ));
    }
}
