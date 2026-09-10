<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Approvals;

use Modules\Core\Models\ApprovalDelegation;

final class DelegationCreated
{
    public function __construct(
        public readonly ApprovalDelegation $delegation,
    ) {}
}
