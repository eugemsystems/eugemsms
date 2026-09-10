<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\RecordSolarGenerationData;
use Modules\Utilities\Models\SolarGeneration;

/**
 * ACT-RecordSolarGeneration (Book H2 OPS-04 §2/BR-OPS-04-014).
 * `grid_offset_kwh` — what solar actually saved — is the generated
 * output capped at what was consumed; generation exceeding same-day
 * consumption wasn't offsetting anything without storage/export data
 * to say otherwise.
 */
final class RecordSolarGenerationAction extends Action
{
    public function execute(RecordSolarGenerationData $data): SolarGeneration
    {
        $gridOffsetKwh = $data->kwhConsumed !== null
            ? min($data->kwhGenerated, $data->kwhConsumed)
            : $data->kwhGenerated;

        return $this->transaction(fn (): SolarGeneration => SolarGeneration::create([
            'school_id' => $data->schoolId,
            'installation_id' => $data->installationId,
            'record_date' => $data->recordDate->toDateString(),
            'kwh_generated' => $data->kwhGenerated,
            'kwh_consumed' => $data->kwhConsumed,
            'battery_state_percent' => $data->batteryStatePercent,
            'grid_offset_kwh' => $gridOffsetKwh,
            'recorded_by' => $data->recordedByUserId,
            'reading_method' => $data->readingMethod,
        ]));
    }
}
