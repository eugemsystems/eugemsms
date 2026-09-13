<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveBillingRunAction;
use Modules\Finance\Domain\Actions\CommitBillingRunAction;
use Modules\Finance\Domain\Actions\ComputeBillingRunAction;
use Modules\Finance\Domain\Actions\CreateAdHocChargeAction;
use Modules\Finance\Domain\Actions\CreateFeeComponentAction;
use Modules\Finance\Domain\Actions\CreateFeeStructureAction;
use Modules\Finance\Domain\DataObjects\ApproveBillingRunData;
use Modules\Finance\Domain\DataObjects\CommitBillingRunData;
use Modules\Finance\Domain\DataObjects\ComputeBillingRunData;
use Modules\Finance\Domain\DataObjects\CreateAdHocChargeData;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * Step 5 of the `serp:seed:finance-*` suite — the fee catalogue, one
 * fee structure per section/residency combination (matching
 * `FeeStructureResolver`'s "exactly one structure wins" rule — every
 * item a category of learner owes has to live on that one structure,
 * never split across several), a handful of ad hoc charges, and a full
 * compute → approve → commit billing run that raises real invoices for
 * every seeded student.
 */
final class SeedFinanceFeesCommand extends Command
{
    protected $signature = 'serp:seed:finance-fees {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed fee components, fee structures, ad hoc charges, and a committed billing run for the demo Finance school.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        SchoolContext::set($school);

        if (FeeComponent::where('code', 'ECDFEE')->exists()) {
            $this->components->warn('Fee data already exists for this school — skipping. Run serp:seed:finance-debtors next, or start over from serp:seed:finance-school-setup with a different --code.');

            return self::SUCCESS;
        }

        $feesClerk = User::firstWhere('email', 'fees.clerk@nyaradzo.example.zw');
        $approver = User::firstWhere('email', 'finance.approver@nyaradzo.example.zw');

        if ($feesClerk === null || $approver === null) {
            $this->components->error('Fees Clerk/Finance Approver users are missing — run serp:seed:finance-users first.');

            return self::FAILURE;
        }

        $year = $school->academicYears()->where('is_current', true)->firstOrFail();

        $components = [];

        $this->components->task('Creating the fee component catalogue', function () use ($school, $feesClerk, &$components): bool {
            $defs = [
                ['code' => 'ECDFEE', 'name' => 'ECD Fees', 'category' => 'tuition', 'income' => '5060'],
                ['code' => 'TUITION', 'name' => 'Tuition Fee', 'category' => 'tuition', 'income' => '5000'],
                ['code' => 'BOARDING', 'name' => 'Boarding Fee', 'category' => 'boarding', 'income' => '5010'],
                ['code' => 'TRANSPORT', 'name' => 'Transport Fee', 'category' => 'transport', 'income' => '5020', 'optional' => true],
                ['code' => 'LEVY', 'name' => 'Development Levy', 'category' => 'levy', 'income' => '5030'],
                ['code' => 'EXAMFEE', 'name' => 'Examination Fee', 'category' => 'examination', 'income' => '5040', 'optional' => true],
                ['code' => 'UNIFORM', 'name' => 'Uniform & Materials', 'category' => 'material', 'income' => '5050', 'optional' => true, 'refundable' => false],
                ['code' => 'COMPLEVY', 'name' => 'Computer Levy', 'category' => 'levy', 'income' => '5070'],
            ];

            $debtors = Account::where('code', '1100')->firstOrFail();

            foreach ($defs as $def) {
                $components[$def['code']] = app(CreateFeeComponentAction::class)->execute(new CreateFeeComponentData(
                    schoolId: $school->id,
                    code: $def['code'],
                    name: $def['name'],
                    category: $def['category'],
                    incomeAccountId: Account::where('code', $def['income'])->firstOrFail()->id,
                    debtorAccountId: $debtors->id,
                    defaultCurrency: 'USD',
                    createdByUserId: $feesClerk->id,
                    isMandatory: ! ($def['optional'] ?? false),
                ));
            }

            return true;
        });

        $structures = [
            'ECD Fees' => [
                'priority' => 10,
                'rules' => [['attribute' => 'section', 'operator' => 'equals', 'value' => 'ECD']],
                'items' => [
                    ['component' => 'ECDFEE', 'basis' => 'flat_per_term', 'amount' => 12000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 1500],
                ],
            ],
            'Primary Day Fees' => [
                'priority' => 20,
                'rules' => [
                    ['attribute' => 'section', 'operator' => 'equals', 'value' => 'PRI'],
                    ['attribute' => 'residency', 'operator' => 'equals', 'value' => 'DAY'],
                ],
                'items' => [
                    ['component' => 'TUITION', 'basis' => 'flat_per_term', 'amount' => 22000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 2500],
                    ['component' => 'COMPLEVY', 'basis' => 'per_day', 'rate' => 20],
                ],
            ],
            'Primary Boarding Fees' => [
                'priority' => 30,
                'rules' => [
                    ['attribute' => 'section', 'operator' => 'equals', 'value' => 'PRI'],
                    ['attribute' => 'residency', 'operator' => 'equals', 'value' => 'BOARDER'],
                ],
                'items' => [
                    ['component' => 'TUITION', 'basis' => 'flat_per_term', 'amount' => 22000],
                    ['component' => 'BOARDING', 'basis' => 'flat_per_term', 'amount' => 28000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 2500],
                    ['component' => 'COMPLEVY', 'basis' => 'per_day', 'rate' => 20],
                ],
            ],
            'Secondary Day Fees' => [
                'priority' => 40,
                'rules' => [
                    ['attribute' => 'section', 'operator' => 'equals', 'value' => 'SEC'],
                    ['attribute' => 'residency', 'operator' => 'equals', 'value' => 'DAY'],
                ],
                'items' => [
                    ['component' => 'TUITION', 'basis' => 'flat_per_term', 'amount' => 32000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 3500],
                    ['component' => 'COMPLEVY', 'basis' => 'per_day', 'rate' => 30],
                ],
            ],
            'Secondary Boarding Fees' => [
                'priority' => 50,
                'rules' => [
                    ['attribute' => 'section', 'operator' => 'equals', 'value' => 'SEC'],
                    ['attribute' => 'residency', 'operator' => 'equals', 'value' => 'BOARDER'],
                ],
                'items' => [
                    ['component' => 'TUITION', 'basis' => 'flat_per_term', 'amount' => 32000],
                    ['component' => 'BOARDING', 'basis' => 'flat_per_term', 'amount' => 30000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 3500],
                    ['component' => 'COMPLEVY', 'basis' => 'per_day', 'rate' => 30],
                ],
            ],
            'Sixth Form Fees' => [
                'priority' => 60,
                'rules' => [['attribute' => 'section', 'operator' => 'equals', 'value' => 'SIX']],
                'items' => [
                    ['component' => 'TUITION', 'basis' => 'flat_per_term', 'amount' => 40000],
                    ['component' => 'LEVY', 'basis' => 'one_off', 'amount' => 4000],
                    ['component' => 'COMPLEVY', 'basis' => 'per_day', 'rate' => 30],
                ],
            ],
        ];

        $this->components->task('Creating '.count($structures).' fee structures', function () use ($school, $year, $feesClerk, $components, $structures): bool {
            // Scoped to term 1 specifically, not left year-wide (term_id:
            // null) — `PeriodGuard` resolves a null-term_id row's
            // writability against the *academic year's own*
            // academic_state/financial_state, and nothing in Book A
            // currently transitions those away from `planned` (only a
            // Term's own state has a transition gateway) — a year-wide
            // fee structure would be refused with "period is planned"
            // even though term 1 itself is open.
            $term = $year->terms()->orderBy('number')->firstOrFail();

            foreach ($structures as $name => $def) {
                app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    name: $name,
                    priority: $def['priority'],
                    rules: $def['rules'],
                    items: array_map(fn (array $item): array => [
                        'component_id' => $components[$item['component']]->id,
                        'billing_basis' => $item['basis'],
                        'currency' => 'USD',
                        'amount_minor' => $item['amount'] ?? null,
                        'unit_rate_minor' => $item['rate'] ?? null,
                    ], $def['items']),
                    createdByUserId: $feesClerk->id,
                    termId: $term->id,
                    status: 'active',
                ));
            }

            return true;
        });

        $this->components->task('Raising 5 ad hoc charges (uniform/exam fees, one above the approval threshold)', function () use ($school, $year, $feesClerk, $approver, $components): bool {
            $term = $year->terms()->orderBy('number')->firstOrFail();
            $students = Student::inRandomOrder()->limit(5)->get();

            foreach ($students as $index => $student) {
                $isUniform = $index % 2 === 0;

                app(CreateAdHocChargeAction::class)->execute(new CreateAdHocChargeData(
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    termId: $term->id,
                    studentId: $student->id,
                    componentId: $components[$isUniform ? 'UNIFORM' : 'EXAMFEE']->id,
                    description: $isUniform ? 'Replacement school jersey — damaged beyond repair' : 'External marking fee — practical subject',
                    unitRateMinor: $isUniform ? 3500 : 8000,
                    currency: 'USD',
                    raisedByUserId: $feesClerk->id,
                    approvedByUserId: $isUniform ? null : $approver->id,
                ));
            }

            return true;
        });

        $billingRunId = null;

        $this->components->task('Computing, approving, and committing a billing run for every learner', function () use ($school, $year, $feesClerk, $approver): bool {
            $term = $year->terms()->orderBy('number')->firstOrFail();

            $run = app(ComputeBillingRunAction::class)->execute(new ComputeBillingRunData(
                schoolId: $school->id,
                academicYearId: $year->id,
                termId: $term->id,
                computedByUserId: $feesClerk->id,
                billingDate: Carbon::now(),
            ));

            app(ApproveBillingRunAction::class)->execute(new ApproveBillingRunData(
                billingRunId: $run->id,
                approvedByUserId: $approver->id,
            ));

            app(CommitBillingRunAction::class)->execute(new CommitBillingRunData(
                billingRunId: $run->id,
                committedByUserId: $approver->id,
                effectiveAt: Carbon::now(),
            ));

            return true;
        });

        $this->components->info('Fees and invoices seeded. Run serp:seed:finance-debtors next.');

        return self::SUCCESS;
    }
}
