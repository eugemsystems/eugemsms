<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Documents;

use Modules\Core\Models\DocumentTemplate;

/**
 * Book A CORE-06 §3. Turns a validated template plus a data payload
 * into the final artefact's raw bytes. `HtmlDocumentRenderer` is the
 * only implementation for now — real PDF output (wkhtmltopdf, dompdf,
 * Browsershot) is a new dependency and is deferred to a later wave,
 * pending approval (Book A CORE-06 §3's file_hash-determinism guarantee
 * is fully testable against HTML bytes in the meantime; swapping the
 * binding to a PDF-producing implementation later changes no calling
 * code).
 */
interface DocumentRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(DocumentTemplate $template, array $data): string;

    public function fileExtension(): string;
}
