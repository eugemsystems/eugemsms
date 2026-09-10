<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\Support\Documents\TemplateValidator;
use Modules\Core\Models\DocumentTemplate;

/**
 * ACT-CreateDocumentTemplate (Book A CORE-06 §4/BR-CORE-06-009/010).
 * Validated against the sandbox before a single row is written —
 * AC-CORE-06-004.
 */
final class CreateDocumentTemplateAction extends Action
{
    public function __construct(
        private readonly TemplateValidator $validator,
    ) {}

    public function execute(CreateDocumentTemplateData $data): DocumentTemplate
    {
        $this->validator->validate($data->templateType, $data->content);

        if ($data->headerContent !== null) {
            $this->validator->validate($data->templateType, $data->headerContent);
        }

        if ($data->footerContent !== null) {
            $this->validator->validate($data->templateType, $data->footerContent);
        }

        return $this->transaction(function () use ($data): DocumentTemplate {
            if ($data->isDefault) {
                DocumentTemplate::where('school_id', $data->schoolId)
                    ->where('template_type', $data->templateType)
                    ->where('section_id', $data->sectionId)
                    ->update(['is_default' => false]);
            }

            return DocumentTemplate::create([
                'school_id' => $data->schoolId,
                'section_id' => $data->sectionId,
                'template_type' => $data->templateType,
                'name' => $data->name,
                'version' => 1,
                'content' => $data->content,
                'styles' => $data->styles,
                'page_size' => $data->pageSize,
                'orientation' => $data->orientation,
                'margins' => $data->margins,
                'header_content' => $data->headerContent,
                'footer_content' => $data->footerContent,
                'is_default' => $data->isDefault,
                'is_active' => true,
                'created_by' => $data->createdByUserId,
                'updated_by' => $data->createdByUserId,
            ]);
        });
    }
}
