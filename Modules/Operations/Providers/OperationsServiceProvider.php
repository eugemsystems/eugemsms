<?php

declare(strict_types=1);

namespace Modules\Operations\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\CapitalProjectMilestone;
use Modules\Operations\Models\FaultReport;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;
use Modules\Operations\Models\WorkOrderLabour;
use Modules\Operations\Models\WorkOrderPart;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H2 Domain F: Operations & Estates (`OPS-02` -> `OPS-01` -> `OPS-04`
 * -> `OPS-03` -> `OPS-05`/`OPS-06`/`OPS-07`), per the book's own build order.
 *
 * `OPS-02` (Maintenance & Works) built in this pass: fault reporting
 * with a safety fast-path (`ReportFaultAction` ⭐), triage into a work
 * order/duplicate/reject (`TriageFaultReportAction` and its two
 * siblings), the real `FIN-11` budget-check-on-approval-threshold
 * (`CreateWorkOrderAction`/`ApproveWorkOrderAction`), the real `FIN-09`
 * parts linkage (`IssuePartsToWorkOrderAction` ⭐⭐ — a genuine
 * `StoreRequisition` against the work order's own cost centre, cost
 * read back from the real FIFO issue, no shadow figure kept
 * independently), labour costing at the staff member's own rate or a
 * configured default, completion/verification with the requester-only
 * verify gate and SLA-against-target-completion, preventive generation
 * for `trigger_type = 'calendar'` schedules, the real `BRD-01` hostel
 * damage link (`RaiseWorkOrderForHostelDamageAction` — the column was
 * left waiting for this module in Book F), and capital project
 * tracking with a real `FIN-10` capitalisation call on completion.
 *
 * Two deliberate, documented boundaries:
 *  - `BR-OPS-02-010`'s usage-based schedule triggering (`OPS-01`
 *    odometer readings, `OPS-04` generator hours) is skipped in
 *    `GeneratePreventiveWorkOrdersAction` — neither module exists yet.
 *  - `BR-OPS-02-007`'s contractor cost never gets a real FK from
 *    `FIN-08`'s `supplier_invoices` to a work order — that table
 *    carries no generic source column, unlike `store_requisitions`/
 *    `purchase_requisitions`, and it's an already-gated Book H1 table.
 *    `RecordContractorCostAction` verifies the invoice's contractor
 *    and school match but doesn't enforce the link at the schema level.
 */
class OperationsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Operations';

    protected string $nameLower = 'operations';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerNotificationKeys();
    }

    /**
     * Book H2 OPS-02 §7.
     */
    private function registerNotificationKeys(): void
    {
        $keys = [
            ['maintenance.safety_fault_reported', ['report.report_number', 'report.location'], true],
            ['maintenance.work_order_verification_escalated', ['work_order.work_order_number'], true],
            ['maintenance.critical_asset_service_overdue', ['asset.name', 'asset.code'], true],
            ['maintenance.preventive_work_order_generated', ['work_order.work_order_number', 'schedule.name'], false],
            ['maintenance.parts_issued_to_work_order', ['work_order.work_order_number'], false],
            ['maintenance.work_order_completed', ['work_order.work_order_number'], false],
        ];

        foreach ($keys as [$key, $variables, $isUrgent]) {
            NotificationKeyRegistry::register(new NotificationKeyDefinition(
                key: $key,
                variables: $variables,
                defaultChannels: ['email'],
                defaultAudience: 'staff',
                isUrgent: $isUrgent,
                isTransactional: true,
            ));
        }
    }

    /**
     * Book H2 OPS-02 §9.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['maintenance.work_order_approval_threshold_minor', 'int', '50000', 'Estimated work order cost at or above which approval and a budget line are required (BR-OPS-02-004).'],
            ['maintenance.default_trade_hourly_rate_minor', 'int', '500', 'Hourly labour rate used when a staff member has no rate of their own recorded (BR-OPS-02-006).'],
            ['maintenance.verification_escalation_days', 'int', '7', 'Days a completed work order may sit unverified before escalating (BR-OPS-02-012).'],
            ['maintenance.fault_triage_window_hours', 'int', '24', 'Hours a fault report may sit untriaged before the configured window is considered breached (BR-OPS-02-003).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'OPS',
                'group_key' => explode('.', $key)[0],
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => 'text',
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(MaintenanceAsset::class, fn (School $school): MaintenanceAsset => MaintenanceAsset::factory()->for($school)->create());

        TenantModelRegistry::register(FaultReport::class, fn (School $school): FaultReport => FaultReport::factory()->for($school)->create());

        TenantModelRegistry::register(WorkOrder::class, fn (School $school): WorkOrder => WorkOrder::factory()->for($school)->create());

        TenantModelRegistry::register(WorkOrderPart::class, function (School $school): WorkOrderPart {
            $workOrder = WorkOrder::factory()->for($school)->create();

            return WorkOrderPart::factory()->create(['school_id' => $school->id, 'work_order_id' => $workOrder->id]);
        });

        TenantModelRegistry::register(WorkOrderLabour::class, function (School $school): WorkOrderLabour {
            $workOrder = WorkOrder::factory()->for($school)->create();

            return WorkOrderLabour::factory()->create(['school_id' => $school->id, 'work_order_id' => $workOrder->id]);
        });

        TenantModelRegistry::register(MaintenanceSchedule::class, fn (School $school): MaintenanceSchedule => MaintenanceSchedule::factory()->for($school)->create());

        TenantModelRegistry::register(CapitalProject::class, fn (School $school): CapitalProject => CapitalProject::factory()->for($school)->create());

        TenantModelRegistry::register(CapitalProjectMilestone::class, function (School $school): CapitalProjectMilestone {
            $project = CapitalProject::factory()->for($school)->create();

            return CapitalProjectMilestone::factory()->create(['school_id' => $school->id, 'project_id' => $project->id]);
        });
    }
}
