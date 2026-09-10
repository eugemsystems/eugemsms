<?php

declare(strict_types=1);

namespace Modules\Intelligence\Providers;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Registry\NotificationKeyRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\DataObjects\ReportEntityDefinition;
use Modules\Intelligence\Domain\DataObjects\ReportFieldDefinition;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\CustomReport;
use Modules\Intelligence\Models\CustomReportSchedule;
use Modules\Intelligence\Models\ReportExecution;
use Modules\Intelligence\Models\ReportShare;
use Modules\Intelligence\Models\WarehouseSnapshot;
use Modules\Payroll\Models\PayGradeNotch;
use Modules\People\Models\Student;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book J INT-01 — Reporting Engine & Data Warehouse, the field
 * registry every later INT/SAA module assumes exists (§0.3).
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
    }

    private function registerNotificationKeys(): void
    {
        NotificationKeyRegistry::register(new NotificationKeyDefinition(
            key: 'intelligence.scheduled_report_ready',
            variables: ['report.name', 'row_count', 'format'],
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
