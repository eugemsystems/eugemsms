<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Receipt;

/**
 * BR-FIN-04-020 would dispatch an SMS/WhatsApp confirmation from a
 * listener on this event — `CORE-09` (notification dispatch) doesn't
 * exist yet, so nothing listens. The event still fires so that wiring
 * is a pure addition later, not a change to this action.
 */
final class ReceiptPosted
{
    public function __construct(
        public readonly Receipt $receipt,
    ) {}
}
