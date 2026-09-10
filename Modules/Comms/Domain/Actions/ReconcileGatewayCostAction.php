<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\ReconcileGatewayCostData;
use Modules\Comms\Domain\Events\CostReconciliationVariance;
use Modules\Comms\Models\GatewayCostReconciliation;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Notification;

/**
 * ACT-ReconcileGatewayCost (Book I COM-01 §3 ⭐/BR-COM-01-012
 * (AC-COM-01-006)). `system_recorded_minor` sums `notifications.cost_minor`
 * — the same figure `DispatchNotificationAction::accrueBudget()` uses
 * to charge the monthly budget, so this reconciliation is never
 * comparing against a second, independently-computed total. A
 * variance beyond the configured tolerance is flagged, never silently
 * absorbed into the school's spend.
 */
final class ReconcileGatewayCostAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ReconcileGatewayCostData $data): GatewayCostReconciliation
    {
        $gateway = MessageGateway::findOrFail($data->gatewayId);

        [$year, $month] = explode('-', $data->periodMonth);

        $systemRecordedMinor = (int) Notification::where('school_id', $data->schoolId)
            ->where('channel', $gateway->channel)
            ->whereNotNull('cost_minor')
            ->whereYear('created_at', (int) $year)
            ->whereMonth('created_at', (int) $month)
            ->sum('cost_minor');

        return $this->transaction(function () use ($data, $systemRecordedMinor): GatewayCostReconciliation {
            $variance = $data->providerInvoicedMinor !== null ? $data->providerInvoicedMinor - $systemRecordedMinor : null;
            $status = $this->resolveStatus($data->schoolId, $systemRecordedMinor, $variance);

            $reconciliation = GatewayCostReconciliation::updateOrCreate(
                ['school_id' => $data->schoolId, 'gateway_id' => $data->gatewayId, 'period_month' => $data->periodMonth],
                [
                    'system_recorded_minor' => $systemRecordedMinor,
                    'provider_invoiced_minor' => $data->providerInvoicedMinor,
                    'variance_minor' => $variance,
                    'status' => $status,
                ],
            );

            if ($status === 'variance') {
                event(new CostReconciliationVariance($reconciliation));
            }

            return $reconciliation;
        });
    }

    private function resolveStatus(int $schoolId, int $systemRecordedMinor, ?int $variance): string
    {
        if ($variance === null) {
            return 'pending';
        }

        $toleranceMinor = $this->toleranceMinor($schoolId, $systemRecordedMinor);

        return abs($variance) > $toleranceMinor ? 'variance' : 'reconciled';
    }

    private function toleranceMinor(int $schoolId, int $systemRecordedMinor): int
    {
        $tolerancePercent = (int) $this->settings->get('comms.cost_reconciliation_tolerance_percent', new ScopeChain(schoolId: $schoolId));

        return (int) round($systemRecordedMinor * $tolerancePercent / 100);
    }
}
