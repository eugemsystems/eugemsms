<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\RecordLoadSheddingData;
use Modules\Utilities\Models\LoadSheddingSchedule;

/**
 * ACT-RecordLoadShedding (Book H2 OPS-04 §2 🇿🇼). Records either a
 * published schedule entry (`source = 'published'`) or an observed
 * actual outage (`source = 'observed'`) — the same row shape either
 * way, distinguished by which fields are populated.
 */
final class RecordLoadSheddingAction extends Action
{
    public function execute(RecordLoadSheddingData $data): LoadSheddingSchedule
    {
        return $this->transaction(fn (): LoadSheddingSchedule => LoadSheddingSchedule::create([
            'school_id' => $data->schoolId,
            'schedule_date' => $data->scheduleDate->toDateString(),
            'stage' => $data->stage,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'actual_outage_start' => $data->actualOutageStart,
            'actual_outage_end' => $data->actualOutageEnd,
            'was_scheduled' => $data->source === 'observed' ? null : true,
            'source' => $data->source,
            'impact_note' => $data->impactNote,
        ]));
    }
}
