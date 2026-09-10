<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\LearnerRiskScore;

final class RiskScoreRecomputed
{
    public function __construct(
        public readonly LearnerRiskScore $score,
    ) {}
}
