<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveManualJournalAction;
use Modules\Finance\Domain\Actions\CreateManualJournalAction;
use Modules\Finance\Domain\Actions\ReverseJournalAction;
use Modules\Finance\Domain\Actions\SetPostingRuleAction;
use Modules\Finance\Domain\DataObjects\ApproveManualJournalData;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Domain\DataObjects\SetPostingRuleData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\PostingRule;

/**
 * Step 3 of the `serp:seed:finance-*` suite. Exercises the manual
 * journal draft → approve → (one) reverse lifecycle FIN-01 §5 defines,
 * with the Accountant test user raising every journal and the Finance
 * Approver test user approving them — a genuinely different user each
 * time, matching what `ApproveManualJournalAction` itself requires.
 */
final class SeedFinanceLedgerCommand extends Command
{
    protected $signature = 'serp:seed:finance-ledger {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed manual journals (draft/approved/reversed) and a posting rule for the demo Finance school.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        SchoolContext::set($school);

        if (PostingRule::where('event_key', 'fee_billing.tuition')->exists()) {
            $this->components->warn('Ledger data already exists for this school — skipping. Run serp:seed:finance-currency next.');

            return self::SUCCESS;
        }

        $accountant = User::firstWhere('email', 'accountant@nyaradzo.example.zw');
        $approver = User::firstWhere('email', 'finance.approver@nyaradzo.example.zw');

        if ($accountant === null || $approver === null) {
            $this->components->error('Accountant/Finance Approver users are missing — run serp:seed:finance-users first.');

            return self::FAILURE;
        }

        $year = $school->academicYears()->where('is_current', true)->firstOrFail();
        $term = $year->terms()->orderBy('number')->firstOrFail();

        $stationery = Account::where('code', '6020')->firstOrFail();
        $sports = Account::where('code', '6030')->firstOrFail();
        $bank = Account::where('code', '1000')->firstOrFail();
        $retainedEarnings = Account::where('code', '3000')->firstOrFail();

        $this->components->task('Raising 3 manual journals, approving 2 (one left pending), and reversing 1', function () use ($school, $year, $term, $accountant, $approver, $stationery, $sports, $bank, $retainedEarnings): bool {
            $raise = function (string $narration, Account $debit, int $amountMinor, ?Account $credit = null) use ($school, $year, $term, $accountant, $bank): int {
                return app(CreateManualJournalAction::class)->execute(new PostJournalData(
                    schoolId: $school->id,
                    academicYearId: $year->id,
                    termId: $term->id,
                    journalType: 'MANUAL',
                    narration: $narration,
                    lines: [
                        new JournalLineData($debit->id, 'DR', Money::of($amountMinor, Currency::USD)),
                        new JournalLineData(($credit ?? $bank)->id, 'CR', Money::of($amountMinor, Currency::USD)),
                    ],
                    effectiveAt: Carbon::now()->subDays(random_int(1, 20)),
                    postedByUserId: $accountant->id,
                ))->id;
            };

            $stationeryJournalId = $raise('Stationery purchase — bursary office', $stationery, 8500);
            $sportsJournalId = $raise('Sports equipment — inter-house athletics', $sports, 24000);
            // Left as a pending draft on purpose — the approval-queue demo.
            $raise('Opening balance — bank account brought forward', $bank, 500000, $retainedEarnings);

            app(ApproveManualJournalAction::class)->execute(new ApproveManualJournalData(
                journalId: $stationeryJournalId,
                approvedByUserId: $approver->id,
            ));

            app(ApproveManualJournalAction::class)->execute(new ApproveManualJournalData(
                journalId: $sportsJournalId,
                approvedByUserId: $approver->id,
            ));

            app(ReverseJournalAction::class)->execute(new ReverseJournalData(
                journalId: $stationeryJournalId,
                reason: 'Duplicate entry — the same stationery invoice was captured twice this term.',
                reversedByUserId: $approver->id,
            ));

            return true;
        });

        $this->components->task('Registering a posting rule', function () use ($school): bool {
            $tuitionIncome = Account::where('code', '5000')->firstOrFail();
            $debtors = Account::where('code', '1100')->firstOrFail();

            app(SetPostingRuleAction::class)->execute(new SetPostingRuleData(
                schoolId: $school->id,
                eventKey: 'fee_billing.tuition',
                debitAccountId: $debtors->id,
                creditAccountId: $tuitionIncome->id,
            ));

            return true;
        });

        $this->components->info('Ledger data seeded. Run serp:seed:finance-currency next.');

        return self::SUCCESS;
    }
}
