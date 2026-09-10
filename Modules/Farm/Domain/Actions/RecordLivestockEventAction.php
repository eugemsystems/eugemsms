<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Farm\Domain\DataObjects\RecordLivestockEventData;
use Modules\Farm\Domain\Events\LivestockDeathRecorded;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;
use Modules\Stores\Domain\Actions\DisposeAssetAction;
use Modules\Stores\Domain\DataObjects\DisposeAssetData;

/**
 * ACT-RecordLivestockEvent (Book H2 OPS-03 §2 ⭐/BR-OPS-03-012/014).
 * `withdrawal_ends_on` — the hard food-safety block
 * `TransferToKitchenAction` checks — is computed here, from the event
 * date plus the recorded withdrawal period, never entered directly. A
 * death on a capitalised breeding animal disposes the real `FIN-10`
 * asset for real (`DisposeAssetAction`) when the caller supplies what
 * it needs to post; otherwise the death is still recorded, just
 * without that follow-on write-off.
 */
final class RecordLivestockEventAction extends Action
{
    public function __construct(
        private readonly DisposeAssetAction $disposeAsset,
    ) {}

    public function execute(RecordLivestockEventData $data): LivestockEvent
    {
        $livestock = Livestock::findOrFail($data->livestockId);

        $withdrawalEndsOn = $data->withdrawalPeriodDays !== null
            ? $data->eventDate->copy()->addDays($data->withdrawalPeriodDays)
            : null;

        return $this->transaction(function () use ($data, $livestock, $withdrawalEndsOn): LivestockEvent {
            $event = LivestockEvent::create([
                'school_id' => $data->schoolId,
                'livestock_id' => $livestock->id,
                'event_type' => $data->eventType,
                'event_date' => $data->eventDate->toDateString(),
                'head_count_affected' => $data->headCountAffected,
                'description' => $data->description,
                'medication' => $data->medication,
                'dosage' => $data->dosage,
                'withdrawal_period_days' => $data->withdrawalPeriodDays,
                'withdrawal_ends_on' => $withdrawalEndsOn?->toDateString(),
                'weight_kg' => $data->weightKg,
                'cost_minor' => $data->costMinor,
                'performed_by' => $data->performedBy,
                'recorded_by' => $data->recordedByUserId,
            ]);

            if ($data->eventType === 'death') {
                $livestock->update(['status' => 'died', 'disposal_on' => $data->eventDate->toDateString(), 'disposal_reason' => $data->description]);

                if ($livestock->fixed_asset_id !== null && $data->academicYearId !== null && $data->termId !== null && $data->disposalApprovedByUserId !== null) {
                    $this->disposeAsset->execute(new DisposeAssetData(
                        assetId: $livestock->fixed_asset_id,
                        academicYearId: $data->academicYearId,
                        termId: $data->termId,
                        disposalDate: $data->eventDate,
                        disposalMethod: 'write_off',
                        proceedsMinor: 0,
                        reason: $data->description ?? 'Livestock death.',
                        performedByUserId: $data->recordedByUserId,
                        approvedByUserId: $data->disposalApprovedByUserId,
                    ));
                }

                event(new LivestockDeathRecorded($event));
            }

            return $event;
        });
    }
}
