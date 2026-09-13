<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ClearChequeAction;
use Modules\Finance\Domain\Actions\CreateReceiptAction;
use Modules\Finance\Domain\Actions\DeclareTillCountAction;
use Modules\Finance\Domain\Actions\OpenTillSessionAction;
use Modules\Finance\Domain\Actions\ResolveSuspenseItemAction;
use Modules\Finance\Domain\Actions\RevealAndCloseTillSessionAction;
use Modules\Finance\Domain\DataObjects\ClearChequeData;
use Modules\Finance\Domain\DataObjects\CreateReceiptData;
use Modules\Finance\Domain\DataObjects\DeclareTillCountData;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Domain\DataObjects\ResolveSuspenseItemData;
use Modules\Finance\Domain\DataObjects\RevealAndCloseTillSessionData;
use Modules\Finance\Domain\Exceptions\VarianceSignOffRequiredException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\SuspenseItem;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * Step 7 (last) of the `serp:seed:finance-*` suite. Runs
 * `serp:seed:finance-fees` first — every receipt here settles a real
 * invoice that billing run raised. Covers the full FIN-04 surface: two
 * tills, every tender type, a partial payment, an overpayment (credit
 * balance), an uncleared-then-cleared cheque, an unidentified deposit
 * resolved through suspense, a clean cash-up, and a cash-up whose
 * variance is beyond tolerance — closed by the Till Supervisor test
 * user, a genuinely different login from the cashier who declared it.
 */
final class SeedFinanceTillCommand extends Command
{
    protected $signature = 'serp:seed:finance-till {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed till sessions, receipts (every tender type), a suspense resolution, and a variance sign-off for the demo Finance school.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        SchoolContext::set($school);

        if (TillSession::exists()) {
            $this->components->warn('Till data already exists for this school — skipping. The demo Finance dataset is already complete.');

            return self::SUCCESS;
        }

        $cashier1 = User::firstWhere('email', 'cashier.one@nyaradzo.example.zw');
        $cashier2 = User::firstWhere('email', 'cashier.two@nyaradzo.example.zw');
        $supervisor = User::firstWhere('email', 'till.supervisor@nyaradzo.example.zw');

        if ($cashier1 === null || $cashier2 === null || $supervisor === null) {
            $this->components->error('Cashier One/Cashier Two/Till Supervisor users are missing — run serp:seed:finance-users first.');

            return self::FAILURE;
        }

        $year = $school->academicYears()->where('is_current', true)->firstOrFail();
        $term = $year->terms()->orderBy('number')->firstOrFail();

        $mainTill = Till::where('code', 'MAIN')->firstOrFail();
        $boardingTill = Till::where('code', 'BRD')->firstOrFail();

        $suspenseAccount = Account::where('system_key', 'suspense')->firstOrFail();
        $creditBalanceAccount = Account::where('system_key', 'credit_balance')->firstOrFail();
        $unclearedChequeAccount = Account::where('system_key', 'uncleared_cheque')->firstOrFail();
        $cashOverShortAccount = Account::where('system_key', 'cash_over_short')->firstOrFail();

        $invoices = Invoice::where('balance_minor', '>', 0)->inRandomOrder()->limit(6)->get();

        if ($invoices->count() < 6) {
            $this->components->error('Not enough open invoices — run serp:seed:finance-fees first.');

            return self::FAILURE;
        }

        $chequeTenderId = null;
        $mainSession = null;
        $mainSessionCashUsd = 0;

        $this->components->task('Opening the Main Office till for Cashier One and capturing 5 receipts', function () use ($school, $year, $term, $cashier1, $mainTill, $invoices, $suspenseAccount, $creditBalanceAccount, $unclearedChequeAccount, &$chequeTenderId, &$mainSession, &$mainSessionCashUsd): bool {
            $mainSession = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
                schoolId: $school->id,
                academicYearId: $year->id,
                termId: $term->id,
                tillId: $mainTill->id,
                cashierId: $cashier1->id,
                openingFloat: ['USD' => 10000, 'ZWG' => 500000],
            ));

            // 1. Cash, fully settling the invoice.
            app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'guardian', payerName: 'Walk-in parent',
                currency: $invoices[0]->currency,
                tenders: [['tender_type' => 'cash', 'amount_minor' => $invoices[0]->balance_minor, 'currency' => $invoices[0]->currency]],
                receivedByUserId: $cashier1->id, tillSessionId: $mainSession->id, studentId: $invoices[0]->student_id,
                narration: 'Full settlement — demo receipt.',
            ));

            // 2. EcoCash, partial payment.
            $partial = intdiv($invoices[1]->balance_minor, 2);
            app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'guardian', payerName: 'Guardian via EcoCash',
                currency: $invoices[1]->currency,
                tenders: [['tender_type' => 'ecocash', 'amount_minor' => $partial, 'currency' => $invoices[1]->currency, 'reference' => 'EC'.random_int(100000, 999999)]],
                receivedByUserId: $cashier1->id, tillSessionId: $mainSession->id, studentId: $invoices[1]->student_id,
                narration: 'Partial payment — demo receipt.',
            ));

            // 3. Cheque — stays uncleared until serp:seed:finance-till clears it below.
            $chequeReceipt = app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'guardian', payerName: 'Guardian by cheque',
                currency: $invoices[2]->currency,
                tenders: [['tender_type' => 'cheque', 'amount_minor' => $invoices[2]->balance_minor, 'currency' => $invoices[2]->currency, 'reference' => 'CHQ'.random_int(1000, 9999)]],
                receivedByUserId: $cashier1->id, tillSessionId: $mainSession->id, studentId: $invoices[2]->student_id,
                unclearedChequeAccountId: $unclearedChequeAccount->id,
                narration: 'Cheque payment — demo receipt.',
            ));
            $chequeTenderId = $chequeReceipt->tenders->first()->id;

            // 4. Bank transfer, deliberately overpaid (creates a credit balance).
            $overpay = $invoices[3]->balance_minor + 2000;
            app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'guardian', payerName: 'Guardian — advance payment',
                currency: $invoices[3]->currency,
                tenders: [['tender_type' => 'bank_transfer', 'amount_minor' => $overpay, 'currency' => $invoices[3]->currency, 'reference' => 'ZIPIT'.random_int(100000, 999999)]],
                receivedByUserId: $cashier1->id, tillSessionId: $mainSession->id, studentId: $invoices[3]->student_id,
                creditBalanceAccountId: $creditBalanceAccount->id,
                narration: 'Paid ahead for next term — demo receipt.',
            ));

            // 5. Unidentified cash deposit — no learner selected, lands in suspense.
            app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'external', payerName: 'Unknown depositor — ref illegible',
                currency: 'USD',
                tenders: [['tender_type' => 'cash', 'amount_minor' => 4000, 'currency' => 'USD']],
                receivedByUserId: $cashier1->id, tillSessionId: $mainSession->id, studentId: null,
                suspenseAccountId: $suspenseAccount->id,
                narration: 'Unidentified deposit found in the drawer at open — demo receipt.',
            ));

            // Only the two cash tenders above move the physical drawer
            // total — EcoCash/cheque/bank transfer never touch it.
            $mainSessionCashUsd = $invoices[0]->balance_minor + 4000;

            return true;
        });

        $this->components->task('Cashing up Cashier One clean (no variance)', function () use ($cashier1, $cashOverShortAccount, &$mainSession, &$mainSessionCashUsd): bool {
            app(DeclareTillCountAction::class)->execute(new DeclareTillCountData(
                tillSessionId: $mainSession->id,
                declaredClosing: [
                    'USD' => 10000 + $mainSessionCashUsd,
                    'ZWG' => 500000,
                ],
                declaredByUserId: $cashier1->id,
            ));

            app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
                tillSessionId: $mainSession->id,
                closedByUserId: $cashier1->id,
                cashOverShortAccountId: $cashOverShortAccount->id,
            ));

            return true;
        });

        $this->components->task('Clearing the cheque', function () use ($cashier1, $unclearedChequeAccount, &$chequeTenderId): bool {
            app(ClearChequeAction::class)->execute(new ClearChequeData(
                receiptTenderId: $chequeTenderId,
                clearedByUserId: $cashier1->id,
                unclearedChequeAccountId: $unclearedChequeAccount->id,
            ));

            return true;
        });

        $suspenseStudentId = $invoices[4]->student_id;

        $this->components->task('Resolving the suspense item to a learner', function () use ($cashier1, $suspenseAccount, $suspenseStudentId): bool {
            $item = SuspenseItem::where('status', 'unidentified')->firstOrFail();

            app(ResolveSuspenseItemAction::class)->execute(new ResolveSuspenseItemData(
                suspenseItemId: $item->id,
                studentId: $suspenseStudentId,
                resolvedByUserId: $cashier1->id,
                suspenseAccountId: $suspenseAccount->id,
                resolutionNote: 'Matched via admission number written on the deposit slip once the bank confirmed the depositor.',
            ));

            return true;
        });

        $this->components->task('Opening the Boarding till for Cashier Two and forcing a till variance', function () use ($school, $year, $term, $cashier2, $boardingTill, $invoices, $supervisor, $cashOverShortAccount): bool {
            $session = app(OpenTillSessionAction::class)->execute(new OpenTillSessionData(
                schoolId: $school->id,
                academicYearId: $year->id,
                termId: $term->id,
                tillId: $boardingTill->id,
                cashierId: $cashier2->id,
                openingFloat: ['USD' => 5000],
            ));

            app(CreateReceiptAction::class)->execute(new CreateReceiptData(
                schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
                receiptType: 'fee', payerType: 'guardian', payerName: 'Boarder\'s guardian',
                currency: $invoices[5]->currency,
                tenders: [['tender_type' => 'cash', 'amount_minor' => $invoices[5]->balance_minor, 'currency' => $invoices[5]->currency]],
                receivedByUserId: $cashier2->id, tillSessionId: $session->id, studentId: $invoices[5]->student_id,
                narration: 'Boarding fee settlement — demo receipt.',
            ));

            // Declares a count $5 short of what the till actually holds —
            // comfortably beyond the $1 default variance tolerance — so
            // this exercises Till\VarianceApproval exactly as a real
            // shortfall would.
            $expectedCash = 5000 + $invoices[5]->balance_minor;
            app(DeclareTillCountAction::class)->execute(new DeclareTillCountData(
                tillSessionId: $session->id,
                declaredClosing: ['USD' => max(0, $expectedCash - 500)],
                declaredByUserId: $cashier2->id,
            ));

            try {
                app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
                    tillSessionId: $session->id,
                    closedByUserId: $cashier2->id,
                    cashOverShortAccountId: $cashOverShortAccount->id,
                ));
            } catch (VarianceSignOffRequiredException) {
                // Expected — this is the point of the exercise. The
                // Till Supervisor test user closes it from here, the
                // same way Finance\Till\VarianceApproval does.
                app(RevealAndCloseTillSessionAction::class)->execute(new RevealAndCloseTillSessionData(
                    tillSessionId: $session->id,
                    closedByUserId: $supervisor->id,
                    cashOverShortAccountId: $cashOverShortAccount->id,
                    varianceReason: 'Confirmed shortfall with the cashier — likely a miscount during a busy afternoon. Verbal warning issued, till procedures re-briefed.',
                    supervisedByUserId: $supervisor->id,
                ));
            }

            return true;
        });

        $this->components->info('Till and receipting data seeded. The demo Finance dataset is complete.');

        return self::SUCCESS;
    }
}
