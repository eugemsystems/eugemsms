<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\RecordManualHousePointsData;
use Modules\Sport\Models\HousePoint;

/**
 * ACT-RecordManualHousePoints (Book H2 OPS-07 §2, `source_type =
 * 'manual'`). The catch-all for a source this book doesn't have a
 * dedicated listener for yet — including BR-OPS-07-008's fourth
 * source, academic results, which is a documented deferral (no module
 * fires an "academic result posted" domain event to listen for).
 */
final class RecordManualHousePointsAction extends Action
{
    public function execute(RecordManualHousePointsData $data): HousePoint
    {
        return $this->transaction(fn (): HousePoint => HousePoint::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'house_id' => $data->houseId,
            'source_type' => 'manual',
            'points' => $data->points,
            'reason' => $data->reason,
            'awarded_at' => Carbon::now(),
            'awarded_by' => $data->awardedByUserId,
        ]));
    }
}
