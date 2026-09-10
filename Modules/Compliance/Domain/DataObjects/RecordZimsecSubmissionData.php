<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordZimsecSubmissionData
{
    public function __construct(
        public int $registrationId,
        public int $submittedByUserId,
        public ?string $zimsecReference = null,
    ) {}
}
