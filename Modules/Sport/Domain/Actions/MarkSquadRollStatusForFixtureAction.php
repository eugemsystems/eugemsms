<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Models\RollCallRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\MarkSquadRollStatusForFixtureData;
use Modules\Sport\Models\Fixture;

/**
 * ACT-MarkSquadRollStatusForFixture (Book H2 OPS-07 §3 ⭐/BR-OPS-07-005).
 * `'fixture'` is already a first-class `BRD-02` roll-call status
 * (`MarkRollCallAction`'s own `recomputeCounts()` counts it toward
 * `accounted_count`, not `missing_count`) — no new status or schema
 * change needed. `Modules\Boarding` has no batch "mark this whole
 * squad for this roll call" helper of its own, so this thin action
 * loops `MarkRollCallAction` once per squad student per roll call the
 * caller names as falling inside the fixture's window (there is no
 * existing "which roll calls fall in this date/time range" resolver
 * to call instead — the caller, which already knows the school's roll
 * call schedule, supplies the ids). A note is always passed so
 * overriding an auto-populated record never fails
 * `MarkRollCallAction`'s own override-requires-a-note guard.
 */
final class MarkSquadRollStatusForFixtureAction extends Action
{
    public function __construct(
        private readonly MarkRollCallAction $markRollCall,
    ) {}

    /**
     * @return Collection<int, RollCallRecord>
     */
    public function execute(MarkSquadRollStatusForFixtureData $data): Collection
    {
        $fixture = Fixture::findOrFail($data->fixtureId);
        $squad = $fixture->squad_student_ids ?? [];

        $records = new Collection;

        foreach ($data->rollCallIds as $rollCallId) {
            foreach ($squad as $studentId) {
                $records->push($this->markRollCall->execute(new MarkRollCallData(
                    rollCallId: $rollCallId,
                    studentId: $studentId,
                    status: 'fixture',
                    markedByUserId: $data->markedByUserId,
                    note: "Away vs {$fixture->opponent} (fixture #{$fixture->id}).",
                )));
            }
        }

        return $records;
    }
}
