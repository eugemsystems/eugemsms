<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Models\Policy;
use Modules\Compliance\Models\PolicyAcknowledgement;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * ACT-ReportPolicyAcknowledgementStatus (Book H3 CMP-04 §3/BR-CMP-04-
 * 003). Who has and has not read the CURRENT version — never an
 * earlier one, since acknowledgements against a superseded version
 * don't count towards this one (AC-CMP-04-001).
 */
final class ReportPolicyAcknowledgementStatusAction extends Action
{
    /**
     * @return array<string, array{acknowledged: array<int, int>, outstanding: array<int, int>}>
     */
    public function execute(int $policyId): array
    {
        $policy = Policy::findOrFail($policyId);
        $audiences = $policy->acknowledgement_audiences ?? [];

        $acknowledgedIds = PolicyAcknowledgement::where('policy_id', $policy->id)
            ->where('policy_version', $policy->version)
            ->get()
            ->groupBy('acknowledged_by_type')
            ->map(fn ($rows) => $rows->pluck('acknowledged_by_id')->all());

        $report = [];

        foreach ($audiences as $audience) {
            $population = $this->populationFor($policy->school_id, $audience);
            $acknowledged = $acknowledgedIds->get($audience, []);

            $report[$audience] = [
                'acknowledged' => array_values(array_intersect($population, $acknowledged)),
                'outstanding' => array_values(array_diff($population, $acknowledged)),
            ];
        }

        return $report;
    }

    /**
     * @return array<int, int>
     */
    private function populationFor(int $schoolId, string $audience): array
    {
        return match ($audience) {
            'staff' => Staff::where('school_id', $schoolId)->where('status', 'active')->pluck('id')->all(),
            'guardians' => Guardian::where('school_id', $schoolId)->pluck('id')->all(),
            'learners' => Student::where('school_id', $schoolId)->where('status', 'active')->pluck('id')->all(),
            default => [],
        };
    }
}
