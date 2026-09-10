<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Backups;

final readonly class ApproveProductionRestoreData
{
    public function __construct(
        public int $restoreTestId,
        public int $approvedByUserId,
    ) {}
}
