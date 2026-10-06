<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;
use Modules\People\Domain\DataObjects\GenerateStudentIdCardData;
use Modules\People\Models\Student;

/**
 * ACT-GenerateIdCard (Book C PPL-01 §5). A printable ID card for an active
 * learner, from the school's `student_id_card` template (a plain stock layout is
 * created if the school has none). Only learners on roll get one.
 */
final class GenerateStudentIdCardAction extends Action
{
    public const TYPE = 'student_id_card';

    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
    ) {}

    public function execute(GenerateStudentIdCardData $data): Document
    {
        $student = Student::findOrFail($data->studentId);

        if (! in_array($student->status, ['enrolled', 'active', 'suspended'], true)) {
            throw new InvalidArgumentException('Only a learner who is on roll can be given an ID card.');
        }

        TemplateVariableRegistry::register(self::TYPE, ['school.name', 'student.name', 'student.admission_number', 'student.date_of_birth', 'issued_on']);

        $template = DocumentTemplate::query()->where('school_id', $student->school_id)->where('template_type', self::TYPE)->where('is_active', true)->where('is_default', true)->first()
            ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
                schoolId: $student->school_id, templateType: self::TYPE, name: 'Standard ID card', pageSize: 'A6', orientation: 'landscape', isDefault: true,
                content: '<h2>{{ school.name }}</h2><p><strong>{{ student.name }}</strong></p><p>Admission no: {{ student.admission_number }}</p><p>Date of birth: {{ student.date_of_birth }}</p><p>Issued {{ issued_on }}</p>',
                createdByUserId: $data->generatedByUserId,
            ));

        return $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $student->school_id,
            documentType: self::TYPE,
            data: [
                'school' => ['name' => (string) School::findOrFail($student->school_id)->name],
                'student' => ['name' => $student->fullName(), 'admission_number' => $student->admission_number, 'date_of_birth' => $student->date_of_birth->toDateString()],
                'issued_on' => Carbon::today()->toDateString(),
            ],
            generatedByUserId: $data->generatedByUserId,
            templateId: $template->id,
            documentableType: $student->getMorphClass(),
            documentableId: $student->id,
            allocateNumber: false,
            verifiable: true,
        ));
    }
}
