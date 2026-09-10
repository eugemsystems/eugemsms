<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordConsentData
{
    public function __construct(
        public int $schoolId,
        public int $consentTypeId,
        public string $subjectType,
        public int $subjectId,
        public string $grantedByType,
        public int $grantedById,
        public bool $granted,
        public string $method,
        public ?int $witnessStaffId = null,
        public ?string $ipAddress = null,
        public ?string $expiresOn = null,
        public ?int $documentFileId = null,
    ) {}
}
