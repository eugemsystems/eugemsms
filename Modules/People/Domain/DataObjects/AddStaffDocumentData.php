<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AddStaffDocumentData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $documentType,
        public int $fileId,
        public ?string $referenceNumber = null,
        public ?CarbonInterface $issuedOn = null,
        public ?CarbonInterface $expiresOn = null,
    ) {}
}
