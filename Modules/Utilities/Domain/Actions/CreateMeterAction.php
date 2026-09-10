<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\CreateMeterData;
use Modules\Utilities\Models\Meter;

/**
 * ACT-CreateMeter (Book H2 OPS-04 §2 ⭐/BR-OPS-04-009).
 */
final class CreateMeterAction extends Action
{
    public function execute(CreateMeterData $data): Meter
    {
        return $this->transaction(fn (): Meter => Meter::create([
            'school_id' => $data->schoolId,
            'utility_account_id' => $data->utilityAccountId,
            'meter_number' => $data->meterNumber,
            'meter_type' => $data->meterType,
            'location' => $data->location,
            'serves_scope' => $data->servesScope,
            'scope_id' => $data->scopeId,
            'cost_centre_id' => $data->costCentreId,
            'unit' => $data->unit,
            'multiplier' => $data->multiplier,
            'current_balance_units' => 0,
            'low_balance_threshold' => $data->lowBalanceThreshold,
            'is_active' => true,
        ]));
    }
}
