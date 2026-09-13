<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveFeeWaiverAction;
use Modules\Finance\Domain\Actions\ApprovePaymentPlanAction;
use Modules\Finance\Domain\Actions\CheckPaymentPlanBreachesAction;
use Modules\Finance\Domain\Actions\CreateCreditNoteAction;
use Modules\Finance\Domain\Actions\CreatePaymentPlanAction;
use Modules\Finance\Domain\Actions\CreateReminderScheduleAction;
use Modules\Finance\Domain\Actions\RecordDebtorChaseNoteAction;
use Modules\Finance\Domain\Actions\RejectFeeWaiverAction;
use Modules\Finance\Domain\Actions\RequestFeeWaiverAction;
use Modules\Finance\Domain\DataObjects\ApproveFeeWaiverData;
use Modules\Finance\Domain\DataObjects\ApprovePaymentPlanData;
use Modules\Finance\Domain\DataObjects\CreateCreditNoteData;
use Modules\Finance\Domain\DataObjects\CreatePaymentPlanData;
use Modules\Finance\Domain\DataObjects\CreateReminderScheduleData;
use Modules\Finance\Domain\DataObjects\RecordDebtorChaseNoteData;
use Modules\Finance\Domain\DataObjects\RejectFeeWaiverData;
use Modules\Finance\Domain\DataObjects\RequestFeeWaiverData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\Invoice;

/**
 * Step 6 of the `serp:seed:finance-*` suite. Runs `serp:seed:finance-fees`
 * first — every seeded credit note/waiver/payment plan is raised
 * against a real invoice that billing run created. Deliberately leaves
 * some records mid-workflow (a pending waiver, a pending payment plan)
 * rather than approving everything, so the approval-queue screens have
 * something to show.
 */
final class SeedFinanceDebtorsCommand extends Command
{
    protected $signature = 'serp:seed:finance-debtors {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed credit notes, waivers, payment plans, reminder schedules, and chase notes for the demo Finance school.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        SchoolContext::set($school);

        if (CreditNote::exists()) {
            $this->components->warn('Debtor data already exists for this school — skipping. Run serp:seed:finance-till next.');

            return self::SUCCESS;
        }

        $feesClerk = User::firstWhere('email', 'fees.clerk@nyaradzo.example.zw');
        $approver = User::firstWhere('email', 'finance.approver@nyaradzo.example.zw');
        $debtCollector = User::firstWhere('email', 'debt.collector@nyaradzo.example.zw');

        if ($feesClerk === null || $approver === null || $debtCollector === null) {
            $this->components->error('Fees Clerk/Finance Approver/Debt Collector users are missing — run serp:seed:finance-users first.');

            return self::FAILURE;
        }

        $invoices = Invoice::where('status', '!=', 'voided')->inRandomOrder()->limit(20)->get();

        if ($invoices->count() < 10) {
            $this->components->error('Not enough invoices exist yet — run serp:seed:finance-fees first.');

            return self::FAILURE;
        }

        $year = $school->academicYears()->where('is_current', true)->firstOrFail();
        $term = $year->terms()->orderBy('number')->firstOrFail();
        $levy = FeeComponent::where('code', 'LEVY')->firstOrFail();

        $this->components->task('Raising 3 credit notes (subject drop / billing error / goodwill)', function () use ($invoices, $feesClerk, $approver, $levy): bool {
            $reasons = ['subject_dropped', 'billing_error', 'goodwill'];

            foreach ($invoices->slice(0, 3)->values() as $index => $invoice) {
                app(CreateCreditNoteAction::class)->execute(new CreateCreditNoteData(
                    schoolId: $invoice->school_id,
                    academicYearId: $invoice->academic_year_id,
                    termId: $invoice->term_id,
                    studentId: $invoice->student_id,
                    reasonCode: $reasons[$index],
                    reason: 'Demo credit note seeded for testing — '.str_replace('_', ' ', $reasons[$index]).'.',
                    currency: $invoice->currency,
                    lines: [[
                        'component_id' => $levy->id,
                        'description' => 'Partial credit — demo data',
                        'amount_minor' => 1500,
                        'invoice_line_id' => null,
                    ]],
                    raisedByUserId: $feesClerk->id,
                    invoiceId: $invoice->id,
                    approvedByUserId: $approver->id,
                ));
            }

            return true;
        });

        $this->components->task('Requesting 3 waivers (1 approved, 1 rejected, 1 left pending)', function () use ($invoices, $term, $feesClerk, $approver): bool {
            $waiverInvoices = $invoices->slice(3, 3)->values();

            $approved = app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
                schoolId: $waiverInvoices[0]->school_id,
                termId: $term->id,
                studentId: $waiverInvoices[0]->student_id,
                type: 'waiver',
                amountMinor: 5000,
                currency: $waiverInvoices[0]->currency,
                reasonCode: 'hardship',
                reason: 'Family hardship following a retrenchment — bursar agreed a partial waiver for this term only.',
                requestedByUserId: $feesClerk->id,
                invoiceId: $waiverInvoices[0]->id,
            ));

            $contraAccount = Account::where('code', '6010')->firstOrFail();
            $debtors = Account::where('code', '1100')->firstOrFail();

            app(ApproveFeeWaiverAction::class)->execute(new ApproveFeeWaiverData(
                feeWaiverId: $approved->id,
                approvedByUserId: $approver->id,
                contraAccountId: $contraAccount->id,
                debtorAccountId: $debtors->id,
            ));

            $rejected = app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
                schoolId: $waiverInvoices[1]->school_id,
                termId: $term->id,
                studentId: $waiverInvoices[1]->student_id,
                type: 'write_off',
                amountMinor: 20000,
                currency: $waiverInvoices[1]->currency,
                reasonCode: 'uncollectable',
                reason: 'Requested write-off — family relocated with no forwarding contact.',
                requestedByUserId: $feesClerk->id,
                invoiceId: $waiverInvoices[1]->id,
            ));

            app(RejectFeeWaiverAction::class)->execute(new RejectFeeWaiverData(
                feeWaiverId: $rejected->id,
                rejectedByUserId: $approver->id,
            ));

            app(RequestFeeWaiverAction::class)->execute(new RequestFeeWaiverData(
                schoolId: $waiverInvoices[2]->school_id,
                termId: $term->id,
                studentId: $waiverInvoices[2]->student_id,
                type: 'waiver',
                amountMinor: 3000,
                currency: $waiverInvoices[2]->currency,
                reasonCode: 'staff_child',
                reason: 'Staff child discount — pending confirmation of current employment status.',
                requestedByUserId: $feesClerk->id,
                invoiceId: $waiverInvoices[2]->id,
            ));

            return true;
        });

        $this->components->task('Creating 3 payment plans (1 active, 1 pending, 1 about to breach)', function () use ($invoices, $debtCollector, $approver): bool {
            $planInvoices = $invoices->slice(6, 3)->values();

            $active = app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
                schoolId: $planInvoices[0]->school_id,
                studentId: $planInvoices[0]->student_id,
                partyType: 'guardian',
                partyId: $planInvoices[0]->billed_party_id,
                totalMinor: $planInvoices[0]->balance_minor,
                currency: $planInvoices[0]->currency,
                instalmentCount: 3,
                firstDueDate: Carbon::now()->addDays(14),
                createdByUserId: $debtCollector->id,
            ));

            app(ApprovePaymentPlanAction::class)->execute(new ApprovePaymentPlanData(
                paymentPlanId: $active->id,
                approvedByUserId: $approver->id,
            ));

            app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
                schoolId: $planInvoices[1]->school_id,
                studentId: $planInvoices[1]->student_id,
                partyType: 'guardian',
                partyId: $planInvoices[1]->billed_party_id,
                totalMinor: $planInvoices[1]->balance_minor,
                currency: $planInvoices[1]->currency,
                instalmentCount: 4,
                firstDueDate: Carbon::now()->addDays(7),
                createdByUserId: $debtCollector->id,
            ));

            $aboutToBreach = app(CreatePaymentPlanAction::class)->execute(new CreatePaymentPlanData(
                schoolId: $planInvoices[2]->school_id,
                studentId: $planInvoices[2]->student_id,
                partyType: 'guardian',
                partyId: $planInvoices[2]->billed_party_id,
                totalMinor: $planInvoices[2]->balance_minor,
                currency: $planInvoices[2]->currency,
                instalmentCount: 2,
                firstDueDate: Carbon::now()->subDays(20),
                createdByUserId: $debtCollector->id,
            ));

            app(ApprovePaymentPlanAction::class)->execute(new ApprovePaymentPlanData(
                paymentPlanId: $aboutToBreach->id,
                approvedByUserId: $approver->id,
            ));

            // Not `serp:check-payment-plan-breaches` — that command's
            // scheduled-task-run wrapper throws
            // UnregisteredScheduledTaskException outside a full
            // scheduler boot (a pre-existing gap, not something this
            // seeder should paper over by pre-registering a fake task).
            // The action underneath it is what actually flips a
            // breached plan's status, so call it directly.
            app(CheckPaymentPlanBreachesAction::class)->execute();

            return true;
        });

        $this->components->task('Setting up a 3-rung reminder ladder', function () use ($school): bool {
            $rungs = [
                ['name' => 'Friendly reminder', 'days' => 7, 'minimum' => 2000],
                ['name' => 'Second notice', 'days' => 21, 'minimum' => 2000],
                ['name' => 'Final notice before collections referral', 'days' => 45, 'minimum' => 5000],
            ];

            foreach ($rungs as $rung) {
                app(CreateReminderScheduleAction::class)->execute(new CreateReminderScheduleData(
                    schoolId: $school->id,
                    name: $rung['name'],
                    daysAfterDue: $rung['days'],
                    channels: ['sms', 'email'],
                    templateKey: 'finance.fee_reminder',
                    audience: 'fee_responsible',
                    minimumBalanceMinor: $rung['minimum'],
                ));
            }

            return true;
        });

        $this->components->task('Logging 4 debtor chase notes', function () use ($invoices, $debtCollector): bool {
            $outcomes = [
                ['outcome' => 'promised_to_pay', 'note' => 'Spoke to mother, promised payment by month end.', 'next' => Carbon::now()->addDays(10)->toDateString()],
                ['outcome' => 'no_answer', 'note' => 'Called twice, no answer — will try WhatsApp next.', 'next' => Carbon::now()->addDays(3)->toDateString()],
                ['outcome' => 'disputed', 'note' => 'Guardian disputes the boarding charge — says child is a day scholar this term. Escalated to bursar for review.', 'next' => null],
                ['outcome' => 'payment_plan_requested', 'note' => 'Guardian asked about spreading the balance over the term — payment plan raised.', 'next' => null],
            ];

            foreach ($invoices->slice(9, 4)->values() as $index => $invoice) {
                app(RecordDebtorChaseNoteAction::class)->execute(new RecordDebtorChaseNoteData(
                    schoolId: $invoice->school_id,
                    studentId: $invoice->student_id,
                    outcome: $outcomes[$index]['outcome'],
                    note: $outcomes[$index]['note'],
                    recordedByUserId: $debtCollector->id,
                    nextActionOn: $outcomes[$index]['next'],
                ));
            }

            return true;
        });

        $this->components->info('Debtor management data seeded. Run serp:seed:finance-till next.');

        return self::SUCCESS;
    }
}
