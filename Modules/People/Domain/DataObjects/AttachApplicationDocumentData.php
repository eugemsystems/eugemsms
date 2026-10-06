<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class AttachApplicationDocumentData
{
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public string $documentType,
        public int $fileId,
    ) {}
}
