<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordClinicObservationData;
use Modules\Welfare\Models\ClinicObservation;

/**
 * ACT-RecordClinicObservation (Book G BRD-06 §2). Append-only — see
 * `ClinicObservation`'s own guard.
 */
final class RecordClinicObservationAction extends Action
{
    public function execute(RecordClinicObservationData $data): ClinicObservation
    {
        return $this->transaction(fn (): ClinicObservation => ClinicObservation::create([
            'school_id' => $data->schoolId,
            'admission_id' => $data->admissionId,
            'observed_at' => $data->observedAt,
            'temperature_c' => $data->temperatureC,
            'pulse_bpm' => $data->pulseBpm,
            'respiration_rate' => $data->respirationRate,
            'blood_pressure' => $data->bloodPressure,
            'oxygen_saturation' => $data->oxygenSaturation,
            'pain_score' => $data->painScore,
            'notes' => $data->notes,
            'observed_by' => $data->observedByUserId,
        ]));
    }
}
