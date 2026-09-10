<?php

declare(strict_types=1);

namespace Modules\Payroll\Providers;

use App\Models\User;
use Modules\Core\Domain\Registry\CloseChecklistRegistry;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Payroll\Domain\Support\CloseChecks\PayrollPostedAndReturnsPreparedCheck;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayGrade;
use Modules\Payroll\Models\PayGradeNotch;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Models\StaffLoan;
use Modules\Payroll\Models\StaffPayComponent;
use Modules\Payroll\Models\StaffPayStructure;
use Modules\Payroll\Models\StatutoryReturn;
use Modules\People\Models\Staff;
use Nwidart\Modules\Support\ModuleServiceProvider;

/**
 * Book H3 PPL-05 — Payroll & Statutory Deductions. Built earlier than
 * this book's own spec-listed order (before `FIN-13`/`FIN-14`/`FIN-12`/
 * `CMP-01`–`04`) since its only real dependencies — `Modules\Finance`
 * (FIN-01 posting), `Modules\People` (staff, leave, contracts) and
 * `Modules\Core` — were already in place.
 *
 * The full 8-step run lifecycle (§4) is real: `ComputePayrollRunAction`
 * (compute/preview/exceptions in one pass — a `PayrollComputationException`
 * per staff member is collected onto `exception_report`, never fatal to
 * the run, per BR-PPL-05-012), `ApprovePayrollRunAction`, `PostPayrollRunAction`
 * (one journal through the real `Modules\Finance\Domain\Actions\PostJournalAction`,
 * loan balances only move here — never at compute/preview time),
 * `DistributePayslipsAction`, `RecordPayrollPaymentAction` (step 7 —
 * the bank file itself is a normal `Core\Domain\Actions\Files\UploadFileAction`
 * upload under the `payroll_bank_file` category this module registers
 * in `CoreServiceProvider`), and `PrepareStatutoryReturnsAction`
 * (step 8, auto-called from posting) plus the separate annual
 * `PrepareItf16ReturnAction` (BR-PPL-05-023 — reconciles to the
 * twelve monthly `p2_paye` rows the monthly action accumulates,
 * blocking preparation on a mismatch). `CheckStatutoryReturnDeadlinesAction`
 * is the periodic due/overdue scan (BR-PPL-05-021), mirroring this
 * codebase's own established pattern (`Modules\Security`'s
 * `CheckOverdueKeysAction`, `Modules\Sport`'s `CheckOverdueEquipmentAction`).
 *
 * Deliberate, documented deferrals:
 *  - Terminal pay (BR-PPL-05-024 — leave encashment, notice pay,
 *    PPL-04 clearance gate) has no distinct computation path; a
 *    `terminal`-type run currently computes identically to a regular
 *    one.
 *  - Split-currency salary blending (BR-PPL-05-010 —
 *    `usd_portion_percent`/`zwg_portion_percent`) is unsupported;
 *    only a single `payment_currency` per structure is, as
 *    `ComputePayslipAction`'s own docblock already states.
 *  - `pay_components.calculation_method` supports `fixed` and
 *    `percentage_of_basic` only; `percentage_of_gross`/`formula`/
 *    `hourly`/`per_unit` throw `UnsupportedCalculationMethodException`
 *    by name.
 *  - No UI, API, or permission registration exists for this module —
 *    the same boundary held by every other module in this codebase;
 *    nothing in this entire platform has a Livewire/API layer yet.
 */
class PayrollServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payroll';

    protected string $nameLower = 'payroll';

    public function register(): void
    {
        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerTenantModels();
        $this->registerSettingDefinitions();
        $this->registerCloseChecklistItems();
    }

    /**
     * Book H3 FIN-12 §4 — this module's own entry on the real
     * `Modules\Core\Domain\Registry\CloseChecklistRegistry`.
     */
    private function registerCloseChecklistItems(): void
    {
        CloseChecklistRegistry::register(new PayrollPostedAndReturnsPreparedCheck);
    }

    /**
     * Book H3 PPL-05 §8.
     */
    private function registerSettingDefinitions(): void
    {
        $definitions = [
            ['payroll.pay_day_of_month', 'int', '25', 'Default day of the month payroll is paid.'],
            ['payroll.variance_alert_percent', 'int', '15', 'Net-pay variance from the prior month, in percent, above which a staff member appears on the exception report.'],
            ['payroll.block_on_unconfirmed_statutory', 'bool', '1', 'Whether an unconfirmed statutory configuration blocks a payroll run (BR-PPL-05-002).'],
            ['payroll.require_separate_approver', 'bool', '1', 'Whether the run\'s approver must differ from whoever computed it.'],
            ['payroll.statutory_due_day', 'int', '10', 'Day of the following month P2/NSSA/NEC/ZIMDEF/AIDS Levy returns fall due.'],
            ['payroll.itf16_due_date', 'string', '01-31', 'Month-day ITF16 falls due each year.'],
            ['payroll.return_alert_days', 'json', '[7,3,1]', 'Days before a statutory return\'s due date that an alert fires.'],
            ['payroll.allow_negative_net', 'bool', '0', 'Whether a negative net pay may still post (always false in this pass — BR-PPL-05-012).'],
        ];

        foreach ($definitions as [$key, $dataType, $default, $label]) {
            SettingDefinitionRegistry::register($key, [
                'module_code' => 'PPL',
                'group_key' => 'payroll',
                'label' => $label,
                'data_type' => $dataType,
                'default_value' => $default,
                'ui_control' => match ($dataType) {
                    'bool' => 'toggle',
                    'json' => 'tags',
                    default => 'text',
                },
                'lowest_scope' => 'school',
                'is_encrypted' => false,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Book A Part 1.11's tenancy isolation test generator.
     * `StatutoryConfiguration` is deliberately excluded — its
     * nullable `school_id` (null = system default) means it isn't
     * `BelongsToSchool`, so it has no per-school tenancy boundary for
     * the generator to test; see that model's own docblock.
     */
    private function registerTenantModels(): void
    {
        TenantModelRegistry::register(PayGrade::class, fn (School $school): PayGrade => PayGrade::factory()->for($school)->create());

        TenantModelRegistry::register(PayGradeNotch::class, function (School $school): PayGradeNotch {
            $grade = PayGrade::factory()->for($school)->create();

            return PayGradeNotch::factory()->create(['school_id' => $school->id, 'grade_id' => $grade->id]);
        });

        TenantModelRegistry::register(PayComponent::class, fn (School $school): PayComponent => PayComponent::factory()->for($school)->create());

        TenantModelRegistry::register(StaffPayStructure::class, function (School $school): StaffPayStructure {
            $staff = Staff::factory()->for($school)->create();

            return StaffPayStructure::factory()->create(['school_id' => $school->id, 'staff_id' => $staff->id]);
        });

        TenantModelRegistry::register(StaffPayComponent::class, function (School $school): StaffPayComponent {
            $staff = Staff::factory()->for($school)->create();
            $structure = StaffPayStructure::factory()->create(['school_id' => $school->id, 'staff_id' => $staff->id]);
            $component = PayComponent::factory()->for($school)->create();

            return StaffPayComponent::factory()->create([
                'school_id' => $school->id,
                'pay_structure_id' => $structure->id,
                'component_id' => $component->id,
            ]);
        });

        TenantModelRegistry::register(StaffLoan::class, function (School $school): StaffLoan {
            $staff = Staff::factory()->for($school)->create();

            return StaffLoan::factory()->create(['school_id' => $school->id, 'staff_id' => $staff->id]);
        });

        TenantModelRegistry::register(PayrollRun::class, function (School $school): PayrollRun {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $user = User::factory()->create();

            return PayrollRun::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'computed_by' => $user->id,
            ]);
        });

        TenantModelRegistry::register(Payslip::class, function (School $school): Payslip {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $user = User::factory()->create();
            $run = PayrollRun::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'computed_by' => $user->id,
            ]);
            $staff = Staff::factory()->for($school)->create();

            return Payslip::factory()->create([
                'school_id' => $school->id,
                'payroll_run_id' => $run->id,
                'staff_id' => $staff->id,
            ]);
        });

        TenantModelRegistry::register(PayslipLine::class, function (School $school): PayslipLine {
            $year = AcademicYear::factory()->for($school)->create();
            $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
            $user = User::factory()->create();
            $run = PayrollRun::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'computed_by' => $user->id,
            ]);
            $staff = Staff::factory()->for($school)->create();
            $payslip = Payslip::factory()->create([
                'school_id' => $school->id,
                'payroll_run_id' => $run->id,
                'staff_id' => $staff->id,
            ]);

            return PayslipLine::factory()->create(['school_id' => $school->id, 'payslip_id' => $payslip->id]);
        });

        TenantModelRegistry::register(StatutoryReturn::class, fn (School $school): StatutoryReturn => StatutoryReturn::factory()->for($school)->create());
    }
}
