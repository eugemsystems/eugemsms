<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class GenerateDocumentData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $schoolId,
        public string $documentType,
        public array $data,
        public int $generatedByUserId,
        public ?int $templateId = null,
        public ?int $academicYearId = null,
        public ?int $termId = null,
        public ?string $documentableType = null,
        public ?int $documentableId = null,
        public bool $allocateNumber = true,
        public bool $verifiable = false,
    ) {}
}
