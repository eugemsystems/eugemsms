<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class ReviewDisposalQueueItemData
{
    public function __construct(
        public int $itemId,
        public int $reviewedByUserId,
        public string $decision,
        public ?string $deferredUntil = null,
        public ?string $deferralReason = null,
    ) {}
}
