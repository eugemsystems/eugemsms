<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Events;

use Modules\Saas\Models\UsageMeter;

/**
 * Book J SAA-01 §4/BR-SAA-01-003 — fired the moment a metric first
 * crosses the soft-warning threshold for a billing period; actual
 * delivery (email/SMS to the tenant's admin) is a listener this pass
 * does not wire, the same "meant to run on a schedule" boundary every
 * other alert in this book set already draws (see
 * `Modules\Intelligence\Domain\Events\WebhookAutoDisabled`).
 */
final class UsageSoftWarningCrossed
{
    public function __construct(
        public readonly UsageMeter $meter,
    ) {}
}
