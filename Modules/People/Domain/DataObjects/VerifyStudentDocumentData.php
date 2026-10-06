<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class VerifyStudentDocumentData
{
    public function __construct(
        public int $documentId,
        public int $verifiedByUserId,
        public bool $originalSighted = false,
    ) {}
}
