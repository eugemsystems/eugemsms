<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\Consent;

/**
 * Book H3 CMP-03 §3/BR-CMP-03-003 (AC-CMP-03-001). Withdrawal must
 * take effect immediately across every module — e.g. a photography
 * withdrawal removing the learner from publication workflows the same
 * day. This codebase has no publication-workflow module built yet
 * (that's Book I), so this event currently has no listener — the same
 * "fires, nothing consumes it yet" shape as
 * `Modules\Payroll\Domain\Events\UnconfirmedStatutoryConfigBlocking`.
 */
final class ConsentWithdrawn
{
    public function __construct(
        public readonly Consent $consent,
    ) {}
}
