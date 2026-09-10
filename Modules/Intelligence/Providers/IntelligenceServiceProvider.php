<?php

declare(strict_types=1);

namespace Modules\Intelligence\Providers;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\WidgetDefinition;
use Modules\Comms\Domain\DataObjects\WidgetResolverResult;
use Modules\Comms\Domain\Registry\WidgetRegistry;
use Modules\Core\Domain\DataObjects\Files\FileCategoryDefinition;
use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\FileCategoryRegistry;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\Intelligence\Domain\Actions\GetKpiValueAction;
use Modules\Intelligence\Domain\DataObjects\KpiDefinitionEntry;
use Modules\Intelligence\Domain\DataObjects\ReportEntityDefinition;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Domain\Registry\KpiRegistry;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\BoardPack;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;
use Modules\Intelligence\Models\ExecutiveDigest;
use Modules\Intelligence\Models\KpiTarget;
use Modules\Intelligence\Models\ReportExecution;
use Modules\Intelligence\Models\ReportShare;
use Modules\Intelligence\Models\WarehouseSnapshot;
use Modules\Payroll\Models\PayGradeNotch;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book J INT-01/INT-02 — Reporting Engine & Data Warehouse (the field
 * registry every later INT/SAA module assumes exists, §0.3) plus
 * Executive Dashboards, Digests & Board Packs.
 */
class IntelligenceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Intelligence';

    protected string $nameLower = 'intelligence';

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerReportFields();
        $this->registerNotificationKeys();
        $this->registerKpis();
        $this->registerExecutiveWidgets();
        $this->registerFileCategories();
    }

    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'intelligence.scheduled_report_ready',
            variables: ['report.name', 'row_count', 'format'],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));

        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'intelligence.executive_digest',
            variables: ['all_green', 'exception_count'],
            defaultChannels: ['email'],
            defaultAudience: 'user',
        ));
    }

    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(CustomReport::class, fn (School $school): CustomReport => CustomReport::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ReportShare::class, fn (School $school): ReportShare => ReportShare::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(CustomReportSchedule::class, fn (School $school): CustomReportSchedule => CustomReportSchedule::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ReportExecution::class, fn (School $school): ReportExecution => ReportExecution::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(WarehouseSnapshot::class, fn (School $school): WarehouseSnapshot => WarehouseSnapshot::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(KpiTarget::class, fn (School $school): KpiTarget => KpiTarget::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(ExecutiveDigest::class, fn (School $school): ExecutiveDigest => ExecutiveDigest::factory()->create(['school_id' => $school->id]));

        TenantModelRegistry::register(BoardPack::class, fn (School $school): BoardPack => BoardPack::factory()->create(['school_id' => $school->id]));
    }

    /**
     * Book J INT-02 §2/BR-INT-02-001. Two real, code-computed KPIs —
     * `collection_rate` (`Modules\Finance\Models\Invoice`'s own
     * `paid_minor`/`net_minor` cache columns, BR-FIN-03-004, never
     * recomputed from the ledger here) and `average_days_overdue`
     * (a lower-is-better KPI, deliberately included so
     * `GetKpiValueAction`'s direction-aware colour logic has a real
     * non-default case to exercise).
     */
    private function registerKpis(): void
    {
        KpiRegistry::register(new KpiDefinitionEntry(
            key: 'collection_rate',
            moduleCode: 'FIN-03',
            label: 'Fee Collection Rate',
            unit: '%',
            valueResolver: function (int $schoolId): float {
                $invoices = Invoice::where('school_id', $schoolId)->where('status', '!=', 'void')->get(['net_minor', 'paid_minor']);
                $net = (int) $invoices->sum('net_minor');

                if ($net === 0) {
                    return 0.0;
                }

                return round(($invoices->sum('paid_minor') / $net) * 100, 2);
            },
            higherIsBetter: true,
            defaultTargetValue: 92.0,
        ));

        KpiRegistry::register(new KpiDefinitionEntry(
            key: 'average_days_overdue',
            moduleCode: 'FIN-03',
            label: 'Average Days Overdue',
            unit: 'days',
            valueResolver: function (int $schoolId): float {
                $overdue = Invoice::where('school_id', $schoolId)
                    ->where('status', '!=', 'void')
                    ->where('balance_minor', '>', 0)
                    ->where('due_date', '<', Carbon::today())
                    ->get(['due_date']);

                if ($overdue->isEmpty()) {
                    return 0.0;
                }

                return round((float) $overdue->avg(fn (Invoice $invoice): int => (int) Carbon::today()->diffInDays($invoice->due_date)), 2);
            },
            higherIsBetter: false,
            defaultTargetValue: 14.0,
        ));
    }

    /**
     * Book J INT-02 §3/BR-INT-02-001/007. Registers the `executive`
     * persona into `Modules\Comms\Domain\Registry\WidgetRegistry` —
     * the SAME registry every other persona already uses — with a
     * resolver calling the real `GetKpiValueAction` rather than
     * re-deriving a KPI value here.
     */
    private function registerExecutiveWidgets(): void
    {
        WidgetRegistry::register(new WidgetDefinition(
            key: 'collection_rate_executive',
            moduleCode: 'INT-02',
            persona: 'executive',
            title: 'Fee Collection Rate',
            dataEndpoint: '/api/v1/executive/kpis',
            resolver: function (User $user, int $schoolId): ?WidgetResolverResult {
                $academicYear = School::findOrFail($schoolId)->currentAcademicYear();

                if ($academicYear === null) {
                    return null;
                }

                $result = app(GetKpiValueAction::class)->execute('collection_rate', $schoolId, $academicYear->id);

                return new WidgetResolverResult('collection_rate_executive', $result->label, [
                    'current_value' => $result->currentValue,
                    'target_value' => $result->targetValue,
                    'status' => $result->status,
                    'unit' => $result->unit,
                ]);
            },
        ));
    }

    /**
     * Book J INT-02 §3/BR-INT-02-006. Mirrors `close_pack`'s own
     * registration shape (`Modules\Core\Providers\CoreServiceProvider::registerFileCategories()`)
     * — a board pack is a real, content-hashed JSON document, sensitive
     * for the same reason a close pack is (it embeds FIN-12's own
     * financial section unmodified).
     */
    private function registerFileCategories(): void
    {
        FileCategoryRegistry::register(new FileCategoryDefinition(
            'board_pack', 'Board Pack', 'INT-02', ['application/json', 'text/plain'], 10 * 1024 * 1024, isSensitive: true,
        ));
    }

    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['reporting.ad_hoc_row_limit', 'int', '50000', 'Rows an ad hoc report may scan live before being redirected to the warehouse snapshot or a scheduled run.'],
            ['reporting.ad_hoc_time_budget_seconds', 'int', '30', 'Seconds an ad hoc report may run before being redirected.'],
            ['reporting.warehouse_rebuild_hour', 'int', '4', 'Hour the nightly warehouse snapshot rebuild runs, after all other nightly jobs.'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'INT',
                'group_key' => 'reporting',
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
     * Book J INT-01 §2/3 ⭐/BR-INT-01-001 — see `ReportFieldRegistry`'s
     * own docblock for the documented scope boundary. Two real
     * entities: `student` (`Modules\People\Models\Student`) and
     * `pay_grade_notch` (`Modules\Payroll\Models\PayGradeNotch`) —
     * the latter chosen deliberately because it's where
     * `basic_salary_minor`, the spec's OWN worked example of a
     * compensation field requiring `staff.view_compensation`
     * (§3/AC-INT-01-001), is a genuinely real, stored column; `Staff`
     * itself carries no such column to select.
     */
    private function registerReportFields(): void
    {
        ReportFieldRegistry::registerEntity(new ReportEntityDefinition(
            entityKey: 'student',
            moduleCode: 'PPL-01',
            baseModelClass: Student::class,
        ));

        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-01', entityKey: 'student', fieldKey: 'first_name',
            label: 'First Name', dataType: 'string', requiredPermission: 'people.student.view',
        ));
        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-01', entityKey: 'student', fieldKey: 'last_name',
            label: 'Last Name', dataType: 'string', requiredPermission: 'people.student.view',
        ));
        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-01', entityKey: 'student', fieldKey: 'admission_number',
            label: 'Admission Number', dataType: 'string', requiredPermission: 'people.student.view',
        ));
        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-01', entityKey: 'student', fieldKey: 'gender',
            label: 'Gender', dataType: 'enum', requiredPermission: 'people.student.view',
            isAggregatable: true,
        ));

        ReportFieldRegistry::registerEntity(new ReportEntityDefinition(
            entityKey: 'pay_grade_notch',
            moduleCode: 'PPL-04',
            baseModelClass: PayGradeNotch::class,
        ));

        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-04', entityKey: 'pay_grade_notch', fieldKey: 'notch',
            label: 'Notch', dataType: 'number', requiredPermission: 'payroll.pay_grade.view',
        ));
        ReportFieldRegistry::registerField(new ReportFieldDefinition(
            moduleCode: 'PPL-04', entityKey: 'pay_grade_notch', fieldKey: 'basic_salary_minor',
            label: 'Basic Salary', dataType: 'money', requiredPermission: 'staff.view_compensation',
            isAggregatable: true, isSensitive: true,
        ));
    }
}
