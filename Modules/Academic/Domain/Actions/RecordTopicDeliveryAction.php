<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\RecordTopicDeliveryData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;

/**
 * ACT-RecordTopicDelivery (Book K ACA-11 §2/BR-ACA-11-002/AC-ACA-11-002).
 * Stamps the date a planned topic was actually taught, with a note on any
 * variance. A topic cannot be recorded as delivered before its term began
 * or in the future. The status itself (on track / behind) is derived by `ComputeCoverageStatusAction`; nothing here judges the teacher.
 */
final class RecordTopicDeliveryAction extends Action
{
    public function execute(RecordTopicDeliveryData $data): SyllabusCoverageRecord
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);

        $record = SyllabusCoverageRecord::query()
            ->where('scheme_of_work_id', $scheme->id)
            ->where('planned_topic_index', $data->plannedTopicIndex)
            ->first();

        if ($record === null) {
            throw new InvalidArgumentException('That topic is not on this scheme of work.');
        }

        $term = Term::findOrFail($scheme->term_id);

        if ($data->actualDeliveredOn->lt($term->starts_on) || $data->actualDeliveredOn->isAfter(Carbon::today())) {
            throw new InvalidArgumentException('A delivery date must fall between the start of the term and today.');
        }

        $note = $data->varianceNote === null ? null : trim($data->varianceNote);

        if ($note !== null && mb_strlen($note) > 255) {
            throw new InvalidArgumentException('A variance note is limited to 255 characters.');
        }

        return $this->transaction(function () use ($record, $data, $note): SyllabusCoverageRecord {
            $record->update([
                'actual_delivered_on' => $data->actualDeliveredOn->toDateString(),
                'variance_note' => $note === '' ? null : $note,
            ]);

            return $record->fresh();
        });
    }
}
