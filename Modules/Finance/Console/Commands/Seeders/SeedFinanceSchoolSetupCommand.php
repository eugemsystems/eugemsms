<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\Actions\Schools\CreateClassAction;
use Modules\Core\Domain\Actions\Schools\CreateGradeLevelAction;
use Modules\Core\Domain\Actions\Schools\CreateSchoolAction;
use Modules\Core\Domain\Actions\Schools\CreateSectionAction;
use Modules\Core\Domain\Actions\Schools\CreateTenantAction;
use Modules\Core\Domain\Actions\Sessions\CreateAcademicYearAction;
use Modules\Core\Domain\Actions\Sessions\CreateTermAction;
use Modules\Core\Domain\Actions\Sessions\TransitionPeriodStateAction;
use Modules\Core\Domain\Actions\Sessions\UpdateAcademicYearAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\DataObjects\Schools\CreateClassData;
use Modules\Core\Domain\DataObjects\Schools\CreateGradeLevelData;
use Modules\Core\Domain\DataObjects\Schools\CreateSchoolData;
use Modules\Core\Domain\DataObjects\Schools\CreateSectionData;
use Modules\Core\Domain\DataObjects\Schools\CreateTenantData;
use Modules\Core\Domain\DataObjects\Sessions\CreateTermData;
use Modules\Core\Domain\DataObjects\Sessions\CreateYearData;
use Modules\Core\Domain\DataObjects\Sessions\TransitionPeriodData;
use Modules\Core\Domain\DataObjects\Sessions\UpdateAcademicYearData;
use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\ZimbabweanNames;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Finance\Domain\Actions\CreateAccountAction;
use Modules\Finance\Domain\Actions\CreateCostCentreAction;
use Modules\Finance\Domain\Actions\RegisterSchoolCurrencyAction;
use Modules\Finance\Domain\DataObjects\CreateAccountData;
use Modules\Finance\Domain\DataObjects\CreateCostCentreData;
use Modules\Finance\Domain\DataObjects\RegisterSchoolCurrencyData;
use Modules\Finance\Models\Till;
use Modules\People\Domain\Actions\CreateGuardianAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;

/**
 * Step 1 of the `serp:seed:finance-*` demo-data suite (see
 * `Modules/Core/Livewire/Scheduling/DemoData.php` for the full run
 * order). Builds a whole school from scratch — tenant, school, one
 * academic year with three terms (term 1 opened for both academic and
 * financial writes, since a brand-new term defaults to `planned` and
 * `PeriodGuard` refuses writes against it), sections/grades/classes,
 * the chart of accounts (including every system account FIN-04/06 look
 * up by `system_key`), cost centres, currencies, numbering series, two
 * tills, and a realistic Zimbabwean student/guardian roll.
 *
 * Every other `serp:seed:finance-*` command looks this school up again
 * by its `--code` (default `FINDEMO`) rather than needing an id passed
 * around between separate `artisan` invocations.
 */
final class SeedFinanceSchoolSetupCommand extends Command
{
    protected $signature = 'serp:seed:finance-school-setup {--code=FINDEMO : Unique school code for the demo school} {--students=130 : How many students to enrol}';

    protected $description = 'Seed a demo Zimbabwean school (year, terms, sections, chart of accounts, tills, students) for testing the Finance module.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $studentCount = max(10, (int) $this->option('students'));

        if (School::withoutGlobalScopes()->where('code', $code)->exists()) {
            $this->components->warn("A school with code [{$code}] already exists — skipping. Use --code to seed a second demo school, or drop the existing one first.");

            return self::SUCCESS;
        }

        $bootstrap = User::factory()->create([
            'name' => 'Finance Demo Seeder',
            'email' => "seed-bootstrap-{$code}@example.zw",
        ]);

        $this->components->task('Creating tenant and school', function () use ($code, $bootstrap, &$school): bool {
            $tenant = app(CreateTenantAction::class)->execute(new CreateTenantData(
                name: 'Nyaradzo Trust Schools',
                slug: 'nyaradzo-trust-'.mb_strtolower($code),
                type: 'independent',
                contactName: 'Bursary Office',
                contactEmail: 'bursary@nyaradzo.example.zw',
                contactPhone: ZimbabweanNames::mobilePhone(),
            ));

            $school = app(CreateSchoolAction::class)->execute(new CreateSchoolData(
                tenantId: $tenant->id,
                code: $code,
                name: 'Nyaradzo High School',
                category: 'private',
                actingUserId: $bootstrap->id,
                shortName: 'Nyaradzo',
                province: 'Harare',
                district: 'Harare Central',
                baseCurrency: 'USD',
            ));

            SchoolContext::set($school);

            app(AssignUserToSchoolAction::class)->execute(new AssignUserData(
                schoolId: $school->id,
                userId: $bootstrap->id,
                assignedByUserId: $bootstrap->id,
                isPrimary: true,
            ));

            return true;
        });

        /** @var School $school */
        $year = null;
        $term1 = null;

        $this->components->task('Creating academic year and opening Term 1', function () use ($school, $bootstrap, &$year, &$term1): bool {
            // Term 1's own date range must actually straddle *today* —
            // ComputeBillingRunAction pro-rates every fee by comparing a
            // learner's enrolment date against the term's start/end
            // (`prorationFactorFor()`), and a term that has already
            // "ended" relative to the real calendar date produces a
            // proration factor near zero, silently billing everyone
            // ~$0 with no error. `generateThreeTerms: true`'s equal
            // three-way split of a Jan-to-December year has no such
            // guarantee once this command is run any day other than
            // shortly after 1 January, so terms are built by hand here
            // instead, each one term-length centred so term 1 always
            // contains today.
            $term1Start = Carbon::now()->subMonths(1)->startOfMonth();
            $term1End = $term1Start->copy()->addMonths(3)->subDay();
            $term2Start = $term1End->copy()->addDay();
            $term2End = $term2Start->copy()->addMonths(3)->subDay();
            $term3Start = $term2End->copy()->addDay();
            $term3End = $term3Start->copy()->addMonths(3)->subDay();

            $year = app(CreateAcademicYearAction::class)->execute(new CreateYearData(
                schoolId: $school->id,
                name: (string) now()->year,
                startsOn: $term1Start,
                endsOn: $term3End,
                actingUserId: $bootstrap->id,
                generateThreeTerms: false,
            ));

            app(UpdateAcademicYearAction::class)->execute(new UpdateAcademicYearData(
                yearId: $year->id,
                schoolId: $school->id,
                name: $year->name,
                startsOn: $year->starts_on,
                endsOn: $year->ends_on,
                isCurrent: true,
                actingUserId: $bootstrap->id,
            ));

            $term1 = app(CreateTermAction::class)->execute(new CreateTermData(
                schoolId: $school->id,
                academicYearId: $year->id,
                number: 1,
                name: 'Term 1',
                startsOn: $term1Start,
                endsOn: $term1End,
                feeDueOn: $term1Start->copy()->addDays(14),
                actingUserId: $bootstrap->id,
            ));

            app(CreateTermAction::class)->execute(new CreateTermData(
                schoolId: $school->id,
                academicYearId: $year->id,
                number: 2,
                name: 'Term 2',
                startsOn: $term2Start,
                endsOn: $term2End,
                feeDueOn: $term2Start->copy()->addDays(14),
                actingUserId: $bootstrap->id,
            ));

            app(CreateTermAction::class)->execute(new CreateTermData(
                schoolId: $school->id,
                academicYearId: $year->id,
                number: 3,
                name: 'Term 3',
                startsOn: $term3Start,
                endsOn: $term3End,
                feeDueOn: $term3Start->copy()->addDays(14),
                actingUserId: $bootstrap->id,
            ));

            app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
                termId: $term1->id,
                periodType: PeriodType::Academic,
                toState: PeriodState::Open,
                performedByUserId: $bootstrap->id,
            ));

            app(TransitionPeriodStateAction::class)->execute(new TransitionPeriodData(
                termId: $term1->id,
                periodType: PeriodType::Financial,
                toState: PeriodState::Open,
                performedByUserId: $bootstrap->id,
            ));

            // No dedicated gateway sets `terms.is_current` anywhere in the
            // app yet (only `academic_state`/`financial_state` are
            // gateway-guarded) — without this, `SessionContext` has no
            // term to fall back to and the admin UI's session switcher
            // shows nothing selected for a brand-new year.
            $term1->update(['is_current' => true]);

            return true;
        });

        $sections = [];
        $gradeLevels = [];

        $this->components->task('Creating sections, grade levels, and classes', function () use ($school, $year, &$sections, &$gradeLevels): bool {
            $sectionDefs = [
                ['code' => 'ECD', 'name' => 'Early Childhood Development', 'type' => 'ecd', 'sort' => 1],
                ['code' => 'PRI', 'name' => 'Primary School', 'type' => 'primary', 'sort' => 2],
                ['code' => 'SEC', 'name' => 'Secondary School', 'type' => 'secondary', 'sort' => 3],
                ['code' => 'SIX', 'name' => 'Sixth Form', 'type' => 'sixth_form', 'sort' => 4],
            ];

            foreach ($sectionDefs as $def) {
                $sections[$def['code']] = app(CreateSectionAction::class)->execute(new CreateSectionData(
                    schoolId: $school->id,
                    code: $def['code'],
                    name: $def['name'],
                    type: $def['type'],
                    sortOrder: $def['sort'],
                ));
            }

            $gradeDefs = [
                ['section' => 'ECD', 'code' => 'ECDA', 'name' => 'ECD A', 'ordinal' => 0, 'entry' => true],
                ['section' => 'ECD', 'code' => 'ECDB', 'name' => 'ECD B', 'ordinal' => 1],
                ['section' => 'PRI', 'code' => 'G1', 'name' => 'Grade 1', 'ordinal' => 2],
                ['section' => 'PRI', 'code' => 'G2', 'name' => 'Grade 2', 'ordinal' => 3],
                ['section' => 'PRI', 'code' => 'G3', 'name' => 'Grade 3', 'ordinal' => 4],
                ['section' => 'PRI', 'code' => 'G4', 'name' => 'Grade 4', 'ordinal' => 5],
                ['section' => 'PRI', 'code' => 'G5', 'name' => 'Grade 5', 'ordinal' => 6],
                ['section' => 'PRI', 'code' => 'G6', 'name' => 'Grade 6', 'ordinal' => 7],
                ['section' => 'PRI', 'code' => 'G7', 'name' => 'Grade 7', 'ordinal' => 8, 'exam' => true, 'exit' => true],
                ['section' => 'SEC', 'code' => 'F1', 'name' => 'Form 1', 'ordinal' => 9],
                ['section' => 'SEC', 'code' => 'F2', 'name' => 'Form 2', 'ordinal' => 10],
                ['section' => 'SEC', 'code' => 'F3', 'name' => 'Form 3', 'ordinal' => 11],
                ['section' => 'SEC', 'code' => 'F4', 'name' => 'Form 4', 'ordinal' => 12, 'exam' => true, 'exit' => true],
                ['section' => 'SIX', 'code' => 'F5', 'name' => 'Form 5', 'ordinal' => 13],
                ['section' => 'SIX', 'code' => 'F6', 'name' => 'Form 6', 'ordinal' => 14, 'exam' => true, 'exit' => true],
            ];

            foreach ($gradeDefs as $def) {
                $grade = app(CreateGradeLevelAction::class)->execute(new CreateGradeLevelData(
                    schoolId: $school->id,
                    sectionId: $sections[$def['section']]->id,
                    code: $def['code'],
                    name: $def['name'],
                    ordinal: $def['ordinal'],
                    isExamLevel: $def['exam'] ?? false,
                    isEntryLevel: $def['entry'] ?? false,
                    isExitLevel: $def['exit'] ?? false,
                    capacity: 120,
                ));
                $gradeLevels[$def['code']] = $grade;

                app(CreateClassAction::class)->execute(new CreateClassData(
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    gradeLevelId: $grade->id,
                    code: $def['code'].'A',
                    name: $def['name'].' A',
                    capacity: 40,
                ));
            }

            return true;
        });

        $accounts = [];

        $this->components->task('Building the chart of accounts', function () use ($school, $bootstrap, &$accounts): bool {
            $defs = [
                // Assets
                ['code' => '1000', 'name' => 'Bank – USD Current Account', 'type' => 'ASSET', 'currency' => 'USD'],
                ['code' => '1001', 'name' => 'Bank – ZWG Current Account', 'type' => 'ASSET', 'currency' => 'ZWG'],
                ['code' => '1010', 'name' => 'Cash – Main Office Till', 'type' => 'ASSET'],
                ['code' => '1011', 'name' => 'Cash – Boarding Office Till', 'type' => 'ASSET'],
                ['code' => '1100', 'name' => 'Fee Debtors Control', 'type' => 'ASSET', 'control' => true, 'subledger' => 'guardian'],
                ['code' => '1200', 'name' => 'Suspense Account', 'type' => 'ASSET', 'systemKey' => 'suspense'],
                ['code' => '1210', 'name' => 'Uncleared Cheques', 'type' => 'ASSET', 'systemKey' => 'uncleared_cheque'],
                // Liabilities
                ['code' => '2100', 'name' => 'Learner Credit Balances', 'type' => 'LIABILITY', 'systemKey' => 'credit_balance'],
                // Equity
                ['code' => '3000', 'name' => 'Retained Earnings', 'type' => 'EQUITY'],
                // Income
                ['code' => '4100', 'name' => 'FX Unrealised Gain', 'type' => 'INCOME', 'systemKey' => 'fx_unrealised_gain'],
                ['code' => '5000', 'name' => 'Tuition Fees Income', 'type' => 'INCOME'],
                ['code' => '5010', 'name' => 'Boarding Fees Income', 'type' => 'INCOME'],
                ['code' => '5020', 'name' => 'Transport Fees Income', 'type' => 'INCOME'],
                ['code' => '5030', 'name' => 'Levies Income', 'type' => 'INCOME'],
                ['code' => '5040', 'name' => 'Examination Fees Income', 'type' => 'INCOME'],
                ['code' => '5050', 'name' => 'Uniform & Materials Income', 'type' => 'INCOME'],
                ['code' => '5060', 'name' => 'ECD Fees Income', 'type' => 'INCOME'],
                ['code' => '5070', 'name' => 'Computer Levy Income', 'type' => 'INCOME'],
                // Expenses
                ['code' => '4000', 'name' => 'Rounding Adjustments', 'type' => 'EXPENSE', 'systemKey' => 'rounding'],
                ['code' => '4110', 'name' => 'FX Unrealised Loss', 'type' => 'EXPENSE', 'systemKey' => 'fx_unrealised_loss'],
                ['code' => '2200', 'name' => 'Cash Over/Short', 'type' => 'EXPENSE', 'systemKey' => 'cash_over_short'],
                ['code' => '6000', 'name' => 'Bad Debts Written Off', 'type' => 'EXPENSE'],
                ['code' => '6010', 'name' => 'Fee Waivers & Discounts', 'type' => 'EXPENSE'],
                ['code' => '6020', 'name' => 'Stationery Expense', 'type' => 'EXPENSE'],
                ['code' => '6030', 'name' => 'Sports Equipment Expense', 'type' => 'EXPENSE'],
            ];

            foreach ($defs as $def) {
                $accounts[$def['code']] = app(CreateAccountAction::class)->execute(new CreateAccountData(
                    schoolId: $school->id,
                    accountTypeCode: $def['type'],
                    code: $def['code'],
                    name: $def['name'],
                    createdByUserId: $bootstrap->id,
                    isControlAccount: $def['control'] ?? false,
                    subledgerType: $def['subledger'] ?? null,
                    isSystem: isset($def['systemKey']),
                    systemKey: $def['systemKey'] ?? null,
                    currency: $def['currency'] ?? null,
                ));
            }

            return true;
        });

        $this->components->task('Registering cost centres', function () use ($school): bool {
            foreach ([
                ['code' => 'PRI', 'name' => 'Primary Section'],
                ['code' => 'SEC', 'name' => 'Secondary Section'],
                ['code' => 'BRD', 'name' => 'Boarding', 'profit' => true],
                ['code' => 'ADM', 'name' => 'Administration'],
            ] as $def) {
                app(CreateCostCentreAction::class)->execute(new CreateCostCentreData(
                    schoolId: $school->id,
                    code: $def['code'],
                    name: $def['name'],
                    isProfitCentre: $def['profit'] ?? false,
                ));
            }

            return true;
        });

        $this->components->task('Registering currencies (USD base + ZWG)', function () use ($school): bool {
            app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(
                schoolId: $school->id,
                currency: 'USD',
                isBase: true,
                isAcceptedForPayment: true,
                roundingIncrementMinor: 1,
            ));

            app(RegisterSchoolCurrencyAction::class)->execute(new RegisterSchoolCurrencyData(
                schoolId: $school->id,
                currency: 'ZWG',
                isBase: false,
                isAcceptedForPayment: true,
                roundingIncrementMinor: 500,
            ));

            return true;
        });

        $this->components->task('Creating numbering series', function () use ($school, $year): bool {
            foreach (['journal', 'receipt', 'credit_note', 'invoice'] as $documentType) {
                app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
                    schoolId: $school->id,
                    documentType: $documentType,
                    pattern: mb_strtoupper(substr($documentType, 0, 3)).'/{SEQ:6}',
                ));
            }

            app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
                schoolId: $school->id,
                documentType: 'admission',
                pattern: '{SCHOOL}/{SEQ:4}',
                academicYearId: $year->id,
            ));

            return true;
        });

        $this->components->task('Setting up two tills', function () use ($school, $accounts): bool {
            Till::create([
                'school_id' => $school->id,
                'code' => 'MAIN',
                'name' => 'Main Office Till',
                'location' => 'Bursary — Main Block',
                'cash_account_id' => $accounts['1010']->id,
                'bank_account_id' => $accounts['1000']->id,
                'accepted_currencies' => ['USD', 'ZWG'],
                'accepted_tenders' => ['cash', 'ecocash', 'onemoney', 'innbucks', 'zipit', 'bank_transfer', 'cheque', 'card'],
                'is_fiscalised' => false,
                'is_active' => true,
            ]);

            Till::create([
                'school_id' => $school->id,
                'code' => 'BRD',
                'name' => 'Boarding Office Till',
                'location' => 'Boarding House Office',
                'cash_account_id' => $accounts['1011']->id,
                'bank_account_id' => $accounts['1000']->id,
                'accepted_currencies' => ['USD', 'ZWG'],
                'accepted_tenders' => ['cash', 'ecocash', 'bank_transfer'],
                'is_fiscalised' => false,
                'is_active' => true,
            ]);

            return true;
        });

        $this->components->task("Enrolling {$studentCount} students with guardians", function () use ($school, $year, $term1, $bootstrap, $gradeLevels, $studentCount): bool {
            $gradeCodes = array_keys($gradeLevels);
            $perGrade = (int) ceil($studentCount / count($gradeCodes));
            $created = 0;

            foreach ($gradeCodes as $gradeCode) {
                /** @var GradeLevel $grade */
                $grade = $gradeLevels[$gradeCode];
                /** @var SchoolSection $section */
                $section = SchoolSection::withoutGlobalScopes()->findOrFail($grade->section_id);

                for ($i = 0; $i < $perGrade && $created < $studentCount; $i++, $created++) {
                    $gender = random_int(0, 1) === 0 ? 'male' : 'female';
                    $firstName = ZimbabweanNames::firstName($gender);
                    $lastName = ZimbabweanNames::surname();
                    $isBoarder = $section->type !== 'ecd' && random_int(1, 100) <= 25;
                    $isPartTime = $section->type === 'sixth_form' && random_int(1, 100) <= 40;

                    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
                        schoolId: $school->id,
                        academicYearId: $year->id,
                        termId: $term1->id,
                        firstName: $firstName,
                        lastName: $lastName,
                        dateOfBirth: Carbon::now()->subYears(6 + $grade->ordinal)->subDays(random_int(0, 365)),
                        gender: $gender,
                        enrolmentType: $isPartTime ? 'PART_TIME' : 'FULL_TIME',
                        residency: $isBoarder ? 'BOARDER' : 'DAY',
                        sectionId: $section->id,
                        gradeLevelId: $grade->id,
                        entryCohortYear: (int) $year->name,
                        createdByUserId: $bootstrap->id,
                        // Enrolled as of the start of term 1, not "today"
                        // — ComputeBillingRunAction pro-rates a fee item
                        // by comparing the learner's enrolment date
                        // against the term's own date range, and every
                        // seeded learner enrolling mid-term (the default
                        // when this is omitted) would otherwise all bill
                        // a fraction of the full fee rather than the
                        // full-term amount this demo data is meant to
                        // show.
                        enrolledOn: $term1->starts_on,
                    ));

                    $guardianLastName = random_int(1, 100) <= 70 ? $lastName : ZimbabweanNames::surname();
                    $guardianGender = random_int(0, 1) === 0 ? 'male' : 'female';
                    $guardianFirstName = ZimbabweanNames::firstName($guardianGender);

                    $guardian = app(CreateGuardianAction::class)->execute(new CreateGuardianData(
                        schoolId: $school->id,
                        guardianType: 'individual',
                        createdByUserId: $bootstrap->id,
                        title: $guardianGender === 'male' ? 'Mr' : 'Mrs',
                        firstName: $guardianFirstName,
                        lastName: $guardianLastName,
                        primaryPhone: ZimbabweanNames::mobilePhone(),
                        email: ZimbabweanNames::email($guardianFirstName, $guardianLastName),
                    ));

                    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
                        studentId: $student->id,
                        guardianId: $guardian->id,
                        relationship: $guardianGender === 'male' ? 'father' : 'mother',
                        createdByUserId: $bootstrap->id,
                        isPrimaryContact: true,
                        isFeeResponsible: true,
                        mayCollectLearner: true,
                        mayViewFullBalance: true,
                    ));
                }
            }

            return true;
        });

        $this->components->info("Demo school [{$code}] is ready — run serp:seed:finance-users next.");

        return self::SUCCESS;
    }
}
