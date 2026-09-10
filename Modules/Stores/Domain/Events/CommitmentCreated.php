<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\BudgetCommitment;

final class CommitmentCreated
{
    public function __construct(
        public readonly BudgetCommitment $commitment,
    ) {}
}
