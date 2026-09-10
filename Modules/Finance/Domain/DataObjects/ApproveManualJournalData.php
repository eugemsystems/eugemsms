<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ApproveManualJournalData
{
    public function __construct(
        public int $journalId,
        public int $approvedByUserId,
    ) {}
}
