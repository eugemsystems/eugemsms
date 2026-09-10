<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\WhatsAppBusinessAccount;

/**
 * Book I COM-01 §3 ⭐/BR-COM-01-007 (AC-COM-01-004). A banned WhatsApp
 * Business Account is a far larger loss than a paused campaign — this
 * fires the moment the rating reaches the configured pause threshold.
 */
final class WhatsAppQualityDegraded
{
    public function __construct(
        public readonly WhatsAppBusinessAccount $account,
        public readonly string $previousRating,
    ) {}
}
