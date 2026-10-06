<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class RecordGuardianVerificationData
{
    public function __construct(
        public int $schoolId,
        public int $guardianId,
        public string $documentType,
        public ?int $documentFileId = null,
        public ?int $photoFileId = null,
        public ?string $notes = null,
    ) {}
}
