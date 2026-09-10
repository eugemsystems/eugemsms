<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\PaymentIntent;

/**
 * BR-FIN-05-... a listener here would dispatch the parent confirmation
 * SMS/WhatsApp once `CORE-09` (notification dispatch) exists — see
 * `ReceiptPosted`'s identical note.
 */
final class PaymentSettled
{
    public function __construct(
        public readonly PaymentIntent $intent,
    ) {}
}
