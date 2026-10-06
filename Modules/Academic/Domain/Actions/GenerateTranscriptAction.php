<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\GenerateTranscriptData;
use Modules\Academic\Domain\Support\ReportCardTemplate;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * ACT-GenerateTranscript (Book D ACA-05 §6, BR-ACA-05-022). Builds a learner's
 * transcript from their PUBLISHED term results across every year — generated
 * from source, so a transcript issued years later matches the report cards
 * issued at the time. It carries a verification code so a third party can
 * check it. Withheld or unpublished terms are not on it.
 */
final class GenerateTranscriptAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
    ) {}

    public function execute(GenerateTranscriptData $data): Document
    {
        $student = Student::findOrFail($data->studentId);

        $results = TermResult::query()->where('student_id', $student->id)->where('status', 'published')->get();

        if ($results->isEmpty()) {
            throw new InvalidArgumentException('That learner has no published results to put on a transcript.');
        }

        $terms = Term::query()->whereIn('id', $results->pluck('term_id'))->get()->keyBy('id');
        $years = AcademicYear::query()->whereIn('id', $results->pluck('academic_year_id'))->pluck('name', 'id');
        $classes = SchoolClass::query()->whereIn('id', $results->pluck('class_id'))->pluck('name', 'id');
        $subjectResults = TermSubjectResult::query()->where('student_id', $student->id)->whereIn('term_id', $results->pluck('term_id'))->get()->groupBy('term_id');
        $subjectNames = Subject::query()->whereIn('id', $subjectResults->flatten()->pluck('subject_id'))->pluck('name', 'id');

        $termRows = $results
            ->sortBy(fn (TermResult $r): string => ($terms[$r->term_id]->starts_on ?? now())->format('Y-m-d'))
            ->map(fn (TermResult $r): array => [
                'year' => (string) ($years[$r->academic_year_id] ?? ''),
                'name' => (string) ($terms[$r->term_id]->name ?? ''),
                'class' => (string) ($classes[$r->class_id] ?? ''),
                'average_percent' => $r->average_percent === null ? '' : number_format((float) $r->average_percent, 1),
                'promotion' => str_replace('_', ' ', (string) $r->promotion_recommendation),
                'subjects' => ($subjectResults[$r->term_id] ?? collect())->map(fn (TermSubjectResult $s): array => [
                    'name' => (string) ($subjectNames[$s->subject_id] ?? ''),
                    'final_percent' => $s->final_percent === null ? '' : number_format((float) $s->final_percent, 1),
                    'grade' => (string) $s->grade,
                ])->sortBy('name')->values()->all(),
            ])->values()->all();

        ReportCardTemplate::registerVariables();

        return $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $student->school_id,
            documentType: ReportCardTemplate::TRANSCRIPT_TYPE,
            data: [
                'school' => ['name' => (string) School::findOrFail($student->school_id)->name],
                'student' => ['name' => $student->fullName(), 'admission_number' => $student->admission_number, 'date_of_birth' => $student->date_of_birth->toDateString()],
                'issued_on' => Carbon::today()->toDateString(),
                'terms' => $termRows,
            ],
            generatedByUserId: $data->generatedByUserId,
            templateId: $this->template($student->school_id, $data->generatedByUserId)->id,
            documentableType: $student->getMorphClass(),
            documentableId: $student->id,
            allocateNumber: false,
            verifiable: true,
        ));
    }

    private function template(int $schoolId, int $userId): DocumentTemplate
    {
        return DocumentTemplate::query()->where('school_id', $schoolId)->where('template_type', ReportCardTemplate::TRANSCRIPT_TYPE)->where('is_active', true)->where('is_default', true)->first()
            ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
                schoolId: $schoolId, templateType: ReportCardTemplate::TRANSCRIPT_TYPE, name: 'Standard transcript',
                content: ReportCardTemplate::transcriptContent(), isDefault: true, createdByUserId: $userId,
            ));
    }
}
