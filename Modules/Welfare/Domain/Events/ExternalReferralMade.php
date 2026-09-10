<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\ExternalReferral;

final class ExternalReferralMade
{
    public function __construct(
        public readonly ExternalReferral $referral,
    ) {}
}
