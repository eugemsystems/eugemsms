<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Operations\Domain\Events\WorkOrderVerificationEscalated;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-CheckUnverifiedWorkOrders (Book H2 OPS-02 §6/BR-OPS-02-012). A
 * completed order left unverified past
 * `maintenance.verification_escalation_days` escalates — this never
 * changes the order's own status, it only alerts; verification still
 * requires the original requester (`VerifyWorkOrderAction`).
 */
final class CheckUnverifiedWorkOrdersAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, WorkOrder>
     */
    public function execute(int $schoolId): Collection
    {
        $windowDays = (int) $this->settings->get('maintenance.verification_escalation_days', new ScopeChain(schoolId: $schoolId));
        $cutoff = Carbon::now()->subDays($windowDays);

        $overdue = WorkOrder::query()
            ->where('school_id', $schoolId)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->get();

        foreach ($overdue as $workOrder) {
            event(new WorkOrderVerificationEscalated($workOrder));
        }

        return $overdue;
    }
}
