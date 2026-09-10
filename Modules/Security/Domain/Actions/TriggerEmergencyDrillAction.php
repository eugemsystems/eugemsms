<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\TriggerEmergencyDrillData;
use Modules\Security\Models\EmergencyDrill;

/**
 * ACT-TriggerEmergencyDrill (Book H2 OPS-06 §3 ⭐/BR-OPS-06-008/010).
 * `expected_headcount` is the live roster size at the moment the
 * drill is triggered — a real number from
 * `AssembleMusterRollAction`, not a static setting. A real incident
 * uses the identical flow, `drill_type = 'real_incident'`, and the
 * record is retained permanently — nothing about this action treats
 * that case differently.
 */
final class TriggerEmergencyDrillAction extends Action
{
    public function __construct(
        private readonly AssembleMusterRollAction $assembleMusterRoll,
    ) {}

    public function execute(TriggerEmergencyDrillData $data): EmergencyDrill
    {
        $expectedHeadcount = $this->assembleMusterRoll->execute($data->schoolId)->count();

        return $this->transaction(fn (): EmergencyDrill => EmergencyDrill::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'drill_type' => $data->drillType,
            'conducted_at' => $data->conductedAt ?? Carbon::now(),
            'is_announced' => $data->isAnnounced,
            'expected_headcount' => $expectedHeadcount,
            'conducted_by' => $data->conductedByUserId,
        ]));
    }
}
