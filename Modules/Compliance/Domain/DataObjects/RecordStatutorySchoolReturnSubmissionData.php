<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordStatutorySchoolReturnSubmissionData
{
    public function __construct(
        public int $returnId,
        public int $submittedByUserId,
        public ?string $acknowledgementRef = null,
    ) {}
}
