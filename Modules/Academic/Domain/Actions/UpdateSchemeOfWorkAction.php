<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\UpdateSchemeOfWorkData;
use Modules\Academic\Domain\Support\NormalisesPlannedTopics;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-UpdateSchemeOfWork (Book K ACA-11 §3/BR-ACA-11-001). A scheme can be
 * revised only while it is a draft or has been returned by the HOD; a
 * submitted or approved scheme is fixed so coverage is always measured against
 * what was approved. Coverage records follow the topic list: new topics get a
 * record, removed ones lose theirs, kept ones keep their delivery dates.
 */
final class UpdateSchemeOfWorkAction extends Action
{
    public function execute(UpdateSchemeOfWorkData $data): SchemeOfWork
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);

        if (! in_array($scheme->status, ['draft', 'returned'], true)) {
            throw new InvalidStateTransitionException(
                "Scheme of work #{$scheme->id} in [{$scheme->status}] can no longer be edited.",
                ['scheme_of_work_id' => $scheme->id, 'status' => $scheme->status],
            );
        }

        $topics = NormalisesPlannedTopics::normalise($data->plannedTopics);

        return $this->transaction(function () use ($scheme, $topics): SchemeOfWork {
            $scheme->update(['planned_topics' => $topics]);

            SyllabusCoverageRecord::query()->where('scheme_of_work_id', $scheme->id)->where('planned_topic_index', '>=', count($topics))->delete();

            foreach (array_keys($topics) as $index) {
                SyllabusCoverageRecord::query()->firstOrCreate(
                    ['scheme_of_work_id' => $scheme->id, 'planned_topic_index' => $index],
                    ['school_id' => $scheme->school_id],
                );
            }

            return $scheme->fresh();
        });
    }
}
