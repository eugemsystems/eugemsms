<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RecordTopicDeliveryData;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordTopicDelivery (Book K ACA-11 §4/BR-ACA-11-002/
 * AC-ACA-11-002). Just a fact — actually delivered on this date —
 * never a judgement; `ComputeCoverageStatusAction` is where
 * on-track/behind gets decided.
 */
final class RecordTopicDeliveryAction extends Action
{
    public function execute(RecordTopicDeliveryData $data): SyllabusCoverageRecord
    {
        $record = SyllabusCoverageRecord::query()
            ->where('scheme_of_work_id', $data->schemeOfWorkId)
            ->where('planned_topic_index', $data->plannedTopicIndex)
            ->firstOrFail();

        return $this->transaction(function () use ($record, $data): SyllabusCoverageRecord {
            $record->update([
                'actual_delivered_on' => $data->actualDeliveredOn->toDateString(),
                'variance_note' => $data->varianceNote,
            ]);

            return $record->fresh();
        });
    }
}
