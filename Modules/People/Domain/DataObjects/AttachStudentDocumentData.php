<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AttachStudentDocumentData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $documentType,
        public int $fileId,
        public int $uploadedByUserId,
        public ?string $referenceNumber = null,
        public ?CarbonInterface $issuedOn = null,
        public ?CarbonInterface $expiresOn = null,
        public ?string $notes = null,
    ) {}
}
