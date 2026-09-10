<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Support;

use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\People\Models\Staff;
use Modules\Welfare\Domain\Actions\OpenSafeguardingCaseAction;
use Modules\Welfare\Domain\Actions\ReportSafeguardingConcernAction;
use Modules\Welfare\Domain\DataObjects\OpenSafeguardingCaseData;
use Modules\Welfare\Domain\DataObjects\ReportSafeguardingConcernData;
use Modules\Welfare\Models\BehaviourRecord;

/**
 * Book G BRD-08 §0.3/BRD-07 §3 ⭐⭐ — the real implementation, now that
 * `BRD-08` exists. Closes the stub `NullSafeguardingRouter` left open:
 * a `is_safeguarding_trigger` behaviour record opens a
 * `system_triggered` concern and, immediately, a case from it — the
 * disciplinary pause `RecordBehaviourAction` already applies is now
 * backed by a real case a lead can actually act on. Bound in
 * `WelfareServiceProvider`, replacing `NullSafeguardingRouter`. A
 * school that has not yet configured `safeguarding.lead_staff_id`
 * (the setting's own default is an empty string, which its `int`
 * cast turns into `0`, not `null` — checked against a real `Staff`
 * row rather than against `null` for exactly that reason) degrades to
 * "pause only, no case" — the same honest half-built state
 * `RecordBehaviourAction` already documents.
 */
final class SafeguardingRouterImpl implements SafeguardingRouter
{
    public function __construct(
        private readonly ReportSafeguardingConcernAction $reportConcern,
        private readonly OpenSafeguardingCaseAction $openCase,
        private readonly SettingResolver $settings,
    ) {}

    public function route(BehaviourRecord $record): ?int
    {
        $scope = new ScopeChain(schoolId: $record->school_id);
        $leadStaffId = $this->settings->get('safeguarding.lead_staff_id', $scope);
        $lead = $leadStaffId === null ? null : Staff::find((int) $leadStaffId);

        if ($lead === null) {
            return null;
        }

        $concern = $this->reportConcern->execute(new ReportSafeguardingConcernData(
            schoolId: $record->school_id,
            reportSource: 'system_triggered',
            concernCategory: 'other',
            description: "Auto-routed from behaviour record #{$record->id}: {$record->description}",
            reportedAt: $record->occurred_at,
            studentId: $record->student_id,
        ));

        $case = $this->openCase->execute(new OpenSafeguardingCaseData(
            schoolId: $record->school_id,
            studentId: $record->student_id,
            leadStaffId: $lead->id,
            openedByUserId: $record->reported_by,
            category: 'other',
            riskLevel: 'medium',
            summary: "Opened automatically from a safeguarding-trigger behaviour category ({$record->category?->name}).",
            concernId: $concern->id,
        ));

        return $case->id;
    }
}
