<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\AgencyReferral;

final class AgencyReferralMade
{
    public function __construct(
        public readonly AgencyReferral $referral,
    ) {}
}
