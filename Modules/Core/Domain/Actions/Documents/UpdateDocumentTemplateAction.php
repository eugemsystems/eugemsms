<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\UpdateDocumentTemplateData;
use Modules\Core\Domain\Events\Documents\TemplateVersionCreated;
use Modules\Core\Domain\Support\Documents\TemplateValidator;
use Modules\Core\Models\DocumentTemplate;

/**
 * ACT-UpdateDocumentTemplate (Book A CORE-06 BR-CORE-06-007/008).
 * Editing never mutates an existing template row — a new one is
 * created with `version + 1`, and the previous row is deactivated but
 * kept forever, since any `Document` already generated pins that exact
 * row via `documents.template_id`.
 */
final class UpdateDocumentTemplateAction extends Action
{
    public function __construct(
        private readonly TemplateValidator $validator,
    ) {}

    public function execute(UpdateDocumentTemplateData $data): DocumentTemplate
    {
        $previous = DocumentTemplate::withoutGlobalScopes()->findOrFail($data->templateId);

        $content = $data->content ?? $previous->content;
        $headerContent = $data->headerContent ?? $previous->header_content;
        $footerContent = $data->footerContent ?? $previous->footer_content;

        $this->validator->validate($previous->template_type, $content);

        if ($headerContent !== null) {
            $this->validator->validate($previous->template_type, $headerContent);
        }

        if ($footerContent !== null) {
            $this->validator->validate($previous->template_type, $footerContent);
        }

        return $this->transaction(function () use ($previous, $data, $content, $headerContent, $footerContent): DocumentTemplate {
            $previous->forceFill(['is_active' => false, 'is_default' => false])->save();

            $next = DocumentTemplate::create([
                'school_id' => $previous->school_id,
                'section_id' => $previous->section_id,
                'template_type' => $previous->template_type,
                'name' => $data->name ?? $previous->name,
                'version' => $previous->version + 1,
                'content' => $content,
                'styles' => $data->styles ?? $previous->styles,
                'page_size' => $previous->page_size,
                'orientation' => $previous->orientation,
                'margins' => $data->margins ?? $previous->margins,
                'header_content' => $headerContent,
                'footer_content' => $footerContent,
                'is_default' => true,
                'is_active' => true,
                'created_by' => $previous->created_by,
                'updated_by' => $data->updatedByUserId,
            ]);

            event(new TemplateVersionCreated($next));

            return $next;
        });
    }
}
