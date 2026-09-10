<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class UpdateDocumentTemplateData
{
    /**
     * @param  array<string, mixed>|null  $margins
     */
    public function __construct(
        public int $templateId,
        public ?string $content = null,
        public ?string $styles = null,
        public ?string $name = null,
        public ?array $margins = null,
        public ?string $headerContent = null,
        public ?string $footerContent = null,
        public ?int $updatedByUserId = null,
    ) {}
}
