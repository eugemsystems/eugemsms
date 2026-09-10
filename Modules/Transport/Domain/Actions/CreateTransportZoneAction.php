<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\CreateTransportZoneData;
use Modules\Transport\Models\TransportZone;

/**
 * ACT-CreateTransportZone (Book H2 OPS-01 §2 ⭐/BR-OPS-01-006).
 */
final class CreateTransportZoneAction extends Action
{
    public function execute(CreateTransportZoneData $data): TransportZone
    {
        return $this->transaction(fn (): TransportZone => TransportZone::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'max_distance_km' => $data->maxDistanceKm,
            'fee_component_id' => $data->feeComponentId,
            'termly_fee_minor' => $data->termlyFeeMinor,
            'currency' => $data->currency,
            'is_active' => true,
        ]));
    }
}
