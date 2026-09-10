<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Documents\DocumentRenderer;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Domain\Events\Documents\DocumentGenerated;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Documents\VerificationCodeGenerator;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;

/**
 * ACT-GenerateDocument (Book A CORE-06 §3/§10 `JOB-GenerateDocument`'s
 * synchronous core — the queue wrapper is a later wave). Number
 * allocation happens inside the SAME transaction as the document row
 * (BR-CORE-06-001's "critical detail"): if rendering or storing fails
 * after the number is allocated, the whole transaction rolls back and
 * the sequence is never consumed (AC-CORE-06-002).
 */
final class GenerateDocumentAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly DocumentRenderer $renderer,
        private readonly VerificationCodeGenerator $verificationCodes,
    ) {}

    public function execute(GenerateDocumentData $data): Document
    {
        $template = $this->resolveTemplate($data);

        return $this->transaction(function () use ($data, $template): Document {
            $number = $data->allocateNumber ? $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: $data->documentType,
                allocatedByUserId: $data->generatedByUserId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                documentableType: $data->documentableType,
                documentableId: $data->documentableId,
            )) : null;

            $rendered = $this->renderer->render($template, $data->data);
            $hash = hash('sha256', $rendered);

            $path = "documents/{$data->schoolId}/{$hash}.{$this->renderer->fileExtension()}";
            Storage::disk('local')->put($path, $rendered);

            $document = Document::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'document_type' => $data->documentType,
                'template_id' => $template->id,
                'template_version' => $template->version,
                'number' => $number?->formatted_number,
                'documentable_type' => $data->documentableType,
                'documentable_id' => $data->documentableId,
                'file_path' => $path,
                'file_hash' => $hash,
                'file_size' => strlen($rendered),
                'verification_code' => $data->verifiable ? $this->verificationCodes->generate() : null,
                'generated_by' => $data->generatedByUserId,
                'generated_at' => Carbon::now(),
            ]);

            if ($number !== null) {
                $number->forceFill([
                    'documentable_type' => $document->getMorphClass(),
                    'documentable_id' => $document->id,
                    'status' => 'used',
                    'used_at' => Carbon::now(),
                ])->save();
            }

            event(new DocumentGenerated($document));

            return $document;
        });
    }

    private function resolveTemplate(GenerateDocumentData $data): DocumentTemplate
    {
        $query = DocumentTemplate::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('template_type', $data->documentType)
            ->where('is_active', true);

        $template = $data->templateId !== null
            ? DocumentTemplate::withoutGlobalScopes()->findOrFail($data->templateId)
            : $query->where('is_default', true)->first();

        if ($template === null) {
            throw new class("No active default template for [{$data->documentType}].") extends DomainException
            {
                public function errorCode(): string
                {
                    return 'DOCUMENT_TEMPLATE_NOT_FOUND';
                }
            };
        }

        return $template;
    }
}
