<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Welfare\Domain\DataObjects\IssueSanctionData;
use Modules\Welfare\Domain\DataObjects\TriggerRuleEvaluation;
use Modules\Welfare\Domain\Events\TriggerThresholdReached;
use Modules\Welfare\Models\BehaviourRecord;
use Modules\Welfare\Models\BehaviourTriggerRule;

/**
 * ACT-EvaluateTriggerRules (Book G BRD-07 §2/BR-BRD-07-003/004/
 * AC-BRD-07-001). Every rule always fires `TriggerThresholdReached` —
 * a human sees the suggestion regardless. Reconciling BR-BRD-07-003
 * ("automatic sanctioning is available") with BR-BRD-07-004's
 * unconditional "no sanction is applied by the system alone, a named
 * person issues every sanction"): an `is_automatic` rule only actually
 * calls `IssueSanctionAction` when the school has additionally named a
 * real person for it (`behaviour.automatic_sanction_issuer_user_id`)
 * — the system triggers the call, but a specific, configured human is
 * still the one issuing it, never an anonymous system actor. With no
 * issuer configured, an automatic rule degrades to suggest-only, the
 * same as a non-automatic one.
 */
final class EvaluateTriggerRulesAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly IssueSanctionAction $issueSanction,
    ) {}

    /**
     * @return Collection<int, TriggerRuleEvaluation>
     */
    public function execute(int $schoolId, int $academicYearId, int $termId, int $studentId): Collection
    {
        $rules = BehaviourTriggerRule::query()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->get();

        $evaluations = collect();

        foreach ($rules as $rule) {
            if (! $this->isTriggered($rule, $schoolId, $studentId)) {
                continue;
            }

            event(new TriggerThresholdReached($rule, $studentId));

            $applied = false;

            if ($rule->is_automatic) {
                $scope = new ScopeChain(schoolId: $schoolId);
                $issuerId = $this->settings->get('behaviour.automatic_sanction_issuer_user_id', $scope);

                if ($issuerId !== null) {
                    $this->issueSanction->execute(new IssueSanctionData(
                        schoolId: $schoolId,
                        academicYearId: $academicYearId,
                        termId: $termId,
                        studentId: $studentId,
                        sanctionTypeId: $rule->suggested_sanction_id,
                        behaviourRecordIds: [],
                        reason: "Automatic sanction — trigger rule '{$rule->name}'.",
                        startsOn: now(),
                        issuedByUserId: (int) $issuerId,
                    ));
                    $applied = true;
                }
            }

            $evaluations->push(new TriggerRuleEvaluation($rule->id, $studentId, $rule->suggested_sanction_id, $applied));
        }

        return $evaluations;
    }

    private function isTriggered(BehaviourTriggerRule $rule, int $schoolId, int $studentId): bool
    {
        $windowStart = $rule->window_days !== null ? now()->subDays($rule->window_days) : null;

        return match ($rule->trigger_type) {
            'points_threshold' => $this->demeritTotal($schoolId, $studentId, $windowStart) >= (int) $rule->demerit_threshold,
            'repeat_category' => $this->categoryCount($schoolId, $studentId, $rule->category_id, $windowStart) >= (int) $rule->repeat_count,
            'severity_single' => $this->categoryCount($schoolId, $studentId, $rule->category_id, $windowStart) >= 1,
            default => false,
        };
    }

    private function demeritTotal(int $schoolId, int $studentId, ?CarbonInterface $windowStart): int
    {
        $query = BehaviourRecord::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('polarity', 'negative');

        if ($windowStart !== null) {
            $query->where('occurred_at', '>=', $windowStart);
        }

        return (int) abs((float) $query->sum('points'));
    }

    private function categoryCount(int $schoolId, int $studentId, ?int $categoryId, ?CarbonInterface $windowStart): int
    {
        if ($categoryId === null) {
            return 0;
        }

        $query = BehaviourRecord::query()
            ->where('school_id', $schoolId)
            ->where('student_id', $studentId)
            ->where('category_id', $categoryId);

        if ($windowStart !== null) {
            $query->where('occurred_at', '>=', $windowStart);
        }

        return $query->count();
    }
}
