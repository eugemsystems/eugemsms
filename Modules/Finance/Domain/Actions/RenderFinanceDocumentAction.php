<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\GenerateDocumentAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\GenerateDocumentData;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;
use Modules\Finance\Domain\Support\FinanceDocumentTemplates;

/**
 * Shared step for the three finance documents (FIN-03 BR-FIN-03-021): make sure
 * the school has a default template of the type, then render through CORE-06.
 * The number is NOT allocated here — an invoice or receipt already has the
 * number it was issued under; this only records the printable document.
 */
final class RenderFinanceDocumentAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly GenerateDocumentAction $generateDocument,
        private readonly CreateDocumentTemplateAction $createTemplate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $schoolId, string $type, array $data, int $generatedByUserId, ?int $academicYearId, ?int $termId, ?string $documentableType = null, ?int $documentableId = null, bool $verifiable = false): Document
    {
        FinanceDocumentTemplates::registerVariables();

        $template = DocumentTemplate::query()->where('school_id', $schoolId)->where('template_type', $type)->where('is_active', true)->where('is_default', true)->first()
            ?? $this->createTemplate->execute(new CreateDocumentTemplateData(
                schoolId: $schoolId, templateType: $type, name: 'Standard '.$type, content: FinanceDocumentTemplates::content($type), isDefault: true, createdByUserId: $generatedByUserId,
            ));

        return $this->generateDocument->execute(new GenerateDocumentData(
            schoolId: $schoolId, documentType: $type, data: $data, generatedByUserId: $generatedByUserId, templateId: $template->id,
            academicYearId: $academicYearId, termId: $termId, documentableType: $documentableType, documentableId: $documentableId,
            allocateNumber: false, verifiable: $verifiable,
        ));
    }
}
