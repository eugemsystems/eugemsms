<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Modules\Core\Domain\Contracts\Documents\DocumentRenderer;
use Modules\Core\Models\DocumentTemplate;

/**
 * Book A CORE-06 §3. Wraps the rendered body with the template's
 * header/footer/styles into one self-contained HTML document. Purely a
 * function of (template content, styles, header, footer, data) — no
 * timestamps, no randomness — which is exactly what BR-CORE-06-011's
 * determinism guarantee requires.
 */
final class HtmlDocumentRenderer implements DocumentRenderer
{
    public function __construct(
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(DocumentTemplate $template, array $data): string
    {
        $body = $this->templateRenderer->render($template->content, $data);
        $header = $template->header_content !== null ? $this->templateRenderer->render($template->header_content, $data) : '';
        $footer = $template->footer_content !== null ? $this->templateRenderer->render($template->footer_content, $data) : '';
        $styles = $template->styles ?? '';

        return <<<HTML
            <!doctype html>
            <html>
            <head><meta charset="utf-8"><style>{$styles}</style></head>
            <body>
            <header>{$header}</header>
            <main>{$body}</main>
            <footer>{$footer}</footer>
            </body>
            </html>
            HTML;
    }

    public function fileExtension(): string
    {
        return 'html';
    }
}
