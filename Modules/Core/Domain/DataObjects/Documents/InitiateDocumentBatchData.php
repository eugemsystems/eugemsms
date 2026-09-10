<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

final readonly class InitiateDocumentBatchData
{
    public function __construct(
        public int $schoolId,
        public string $documentType,
        public int $templateId,
        public int $totalCount,
        public int $requestedByUserId,
    ) {}
}
