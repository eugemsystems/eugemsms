<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class CreateDocumentTemplateData
{
    /**
     * @param  array<string, mixed>|null  $margins
     */
    public function __construct(
        public int $schoolId,
        public string $templateType,
        public string $name,
        public string $content,
        public ?string $styles = null,
        public ?int $sectionId = null,
        public string $pageSize = 'A4',
        public string $orientation = 'portrait',
        public ?array $margins = null,
        public ?string $headerContent = null,
        public ?string $footerContent = null,
        public bool $isDefault = false,
        public ?int $createdByUserId = null,
    ) {}
}
