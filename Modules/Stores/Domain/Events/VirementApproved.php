<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\BudgetVirement;

final class VirementApproved
{
    public function __construct(
        public readonly BudgetVirement $virement,
    ) {}
}
