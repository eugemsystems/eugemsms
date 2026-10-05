<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\Registry\RiskIndicatorRegistry;
use Modules\Intelligence\Models\RiskScoreWeight;

/**
 * ACT-SetRiskScoreWeight (Book J INT-03 §4/BR-INT-03-003). A school
 * may re-weight or disable an indicator an owning module has
 * registered; it can never invent one — an unregistered key is refused
 * here, not just hidden in the UI. Weights are points of the 0–100
 * composite, so the registered bound is 0–100 (the spec names bounds
 * but states no figures); a `null` weight keeps the indicator's own
 * default.
 */
final class SetRiskScoreWeightAction extends Action
{
    public function execute(int $schoolId, string $indicatorKey, ?float $weight, bool $isEnabled): RiskScoreWeight
    {
        $indicator = RiskIndicatorRegistry::get($indicatorKey);

        if ($indicator === null || $indicator->appliesTo !== 'learner') {
            throw new InvalidArgumentException("[{$indicatorKey}] is not a registered learner risk indicator (BR-INT-03-003).");
        }

        if ($weight !== null && ($weight < 0 || $weight > 100)) {
            throw new InvalidArgumentException('An indicator weight must be between 0 and 100.');
        }

        return $this->transaction(fn (): RiskScoreWeight => RiskScoreWeight::updateOrCreate(
            ['school_id' => $schoolId, 'indicator_key' => $indicatorKey],
            ['weight' => $weight ?? $indicator->defaultWeight, 'is_enabled' => $isEnabled],
        ));
    }
}
