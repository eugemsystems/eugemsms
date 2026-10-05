<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\RecordDrillFindingsData;
use Modules\Security\Models\EmergencyDrill;

/**
 * ACT-RecordDrillFindings (Book H2 OPS-06 §5/BR-OPS-06-010). A new,
 * gap-filling Action for this pass — `emergency_drills.findings`/
 * `.actions_required` had no Action anywhere in the shipped domain
 * layer that ever wrote to them; `TriggerEmergencyDrillAction` sets
 * headcount and `CompleteMusterAction` sets the mustered/unaccounted
 * counts, but neither touches the free-text findings a drill's own
 * review produces afterwards.
 */
final class RecordDrillFindingsAction extends Action
{
    public function execute(int $drillId, RecordDrillFindingsData $data): EmergencyDrill
    {
        $drill = EmergencyDrill::findOrFail($drillId);

        return $this->transaction(fn (): EmergencyDrill => tap($drill)->update([
            'findings' => $data->findings,
            'actions_required' => $data->actionsRequired,
        ]));
    }
}
