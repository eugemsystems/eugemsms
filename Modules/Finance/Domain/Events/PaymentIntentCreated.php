<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\PaymentIntent;

final class PaymentIntentCreated
{
    public function __construct(
        public readonly PaymentIntent $intent,
    ) {}
}
