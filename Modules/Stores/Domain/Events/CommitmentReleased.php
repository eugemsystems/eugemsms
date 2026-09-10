<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\BudgetCommitment;

final class CommitmentReleased
{
    public function __construct(
        public readonly BudgetCommitment $commitment,
        public readonly int $releasedMinor,
    ) {}
}
