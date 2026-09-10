<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\PurchaseRequisition;

final class RequisitionApproved
{
    public function __construct(
        public readonly PurchaseRequisition $requisition,
    ) {}
}
