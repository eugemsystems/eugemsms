<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Documents\DocumentRenderer;
use Modules\Core\Domain\DataObjects\Documents\RegenerateDocumentData;
use Modules\Core\Models\Document;
use Modules\Core\Models\DocumentTemplate;

/**
 * ACT-RegenerateDocument (Book A CORE-06 BR-CORE-06-008/AC-CORE-06-003).
 * Re-renders using the ORIGINAL document's exact template row —
 * `documents.template_id` already pins one specific version (each
 * edit creates a new `document_templates` row rather than mutating an
 * existing one), so this never touches "whatever is current now". No
 * number is reallocated; regenerating a receipt does not mint a new
 * one.
 */
final class RegenerateDocumentAction extends Action
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
    ) {}

    public function execute(RegenerateDocumentData $data): Document
    {
        $original = Document::withoutGlobalScopes()->findOrFail($data->documentId);
        $template = DocumentTemplate::withoutGlobalScopes()->findOrFail($original->template_id);

        $rendered = $this->renderer->render($template, $data->data);
        $hash = hash('sha256', $rendered);
        $path = "documents/{$original->school_id}/{$hash}.{$this->renderer->fileExtension()}";

        Storage::disk('local')->put($path, $rendered);

        return $this->transaction(function () use ($original, $template, $rendered, $hash, $path): Document {
            return Document::create([
                'school_id' => $original->school_id,
                'academic_year_id' => $original->academic_year_id,
                'term_id' => $original->term_id,
                'document_type' => $original->document_type,
                'template_id' => $template->id,
                'template_version' => $template->version,
                'number' => $original->number,
                'documentable_type' => $original->documentable_type,
                'documentable_id' => $original->documentable_id,
                'file_path' => $path,
                'file_hash' => $hash,
                'file_size' => strlen($rendered),
                'generated_by' => $original->generated_by,
                'generated_at' => now(),
            ]);
        });
    }
}
