<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\SupplierPayment;

final class PaymentApproved
{
    public function __construct(
        public readonly SupplierPayment $payment,
    ) {}
}
