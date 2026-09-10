<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class RecordZimsecFeeCollectionData
{
    public function __construct(
        public int $registrationId,
        public int $amountMinor,
        public int $recordedByUserId,
    ) {}
}
