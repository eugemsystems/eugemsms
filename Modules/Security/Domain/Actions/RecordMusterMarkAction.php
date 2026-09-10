<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Models\MusterMark;

/**
 * ACT-RecordMusterMark (Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-008). A
 * marshal tapping present — idempotent, since a marshal re-tapping
 * the same person (or two marshals tapping the same person at
 * different assembly points by mistake) must never error mid-drill;
 * it just records the latest tap.
 */
final class RecordMusterMarkAction extends Action
{
    public function execute(int $schoolId, int $drillId, string $personType, int $personId, int $markedByUserId, ?string $assemblyPoint = null): MusterMark
    {
        return $this->transaction(fn (): MusterMark => MusterMark::updateOrCreate(
            ['drill_id' => $drillId, 'person_type' => $personType, 'person_id' => $personId],
            ['school_id' => $schoolId, 'assembly_point' => $assemblyPoint, 'marked_present_at' => now(), 'marked_by' => $markedByUserId],
        ));
    }
}
