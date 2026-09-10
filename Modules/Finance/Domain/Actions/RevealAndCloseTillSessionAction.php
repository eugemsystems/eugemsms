<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Domain\DataObjects\RevealAndCloseTillSessionData;
use Modules\Finance\Domain\Events\TillSessionClosed;
use Modules\Finance\Domain\Exceptions\TillDeclarationRequiredException;
use Modules\Finance\Domain\Exceptions\VarianceSignOffRequiredException;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptTender;
use Modules\Finance\Models\Till;
use Modules\Finance\Models\TillSession;

/**
 * ACT-RevealAndCloseTillSession (Book B FIN-04 §3 ⭐/§5/BR-FIN-04-003/
 * 004/005 (AC-FIN-04-002/003)). `expected_closing` is computed and
 * revealed only here, and only once a declaration already exists —
 * there is no route to it before that (`TillDeclarationRequiredException`).
 * Variance always posts to Cash Over/Short, never absorbed into fee
 * income; beyond tolerance it additionally needs a written reason and
 * a supervisor who is not the cashier.
 */
final class RevealAndCloseTillSessionAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RevealAndCloseTillSessionData $data): TillSession
    {
        $session = TillSession::findOrFail($data->tillSessionId);

        if ($session->declared_closing === null || $session->status !== 'declaring') {
            throw TillDeclarationRequiredException::forSession($session->id);
        }

        $till = Till::findOrFail($session->till_id);
        $expected = $this->computeExpectedClosing($session, $till);
        $variance = $this->computeVariance($session->declared_closing, $expected);

        $tolerance = (int) $this->settings->get('finance.till_variance_tolerance_minor', new ScopeChain(schoolId: $session->school_id));
        $beyondTolerance = collect($variance)->some(fn (int $amount): bool => abs($amount) > $tolerance);

        if ($beyondTolerance) {
            if ($data->varianceReason === null || trim($data->varianceReason) === '') {
                throw VarianceSignOffRequiredException::missingReason();
            }

            if ($data->supervisedByUserId === null) {
                throw VarianceSignOffRequiredException::missingSupervisor();
            }

            if ($data->supervisedByUserId === $session->cashier_id) {
                throw VarianceSignOffRequiredException::supervisorMustDiffer($session->cashier_id);
            }
        }

        return $this->transaction(function () use ($session, $till, $expected, $variance, $data): TillSession {
            $journalLines = $this->varianceJournalLines($till, $variance, $data->cashOverShortAccountId);
            $journalId = null;

            if ($journalLines !== []) {
                $journal = $this->postJournal->execute(new PostJournalData(
                    schoolId: $session->school_id,
                    academicYearId: $session->academic_year_id,
                    termId: $session->term_id,
                    journalType: 'CASH_OVER_SHORT',
                    narration: "Till session {$session->session_number} variance",
                    lines: $journalLines,
                    effectiveAt: Carbon::now(),
                    postedByUserId: $data->closedByUserId,
                    sourceType: 'till_session',
                    sourceId: $session->id,
                ));

                $journalId = $journal->id;
            }

            $session->update([
                'expected_closing' => $expected,
                'variance' => $variance,
                'variance_reason' => $data->varianceReason,
                'supervised_by' => $data->supervisedByUserId,
                'status' => 'closed',
                'closed_at' => Carbon::now(),
                'journal_id' => $journalId,
            ]);

            event(new TillSessionClosed($session));

            return $session;
        });
    }

    /**
     * @return array<string, int>
     */
    private function computeExpectedClosing(TillSession $session, Till $till): array
    {
        $cashByCurrency = ReceiptTender::query()
            ->where('tender_type', 'cash')
            ->whereIn('receipt_id', Receipt::query()->where('till_session_id', $session->id)->select('id'))
            ->get()
            ->groupBy('currency')
            ->map(fn ($group) => $group->sum('amount_minor'));

        $expected = $session->opening_float;

        foreach ($cashByCurrency as $currency => $amount) {
            $expected[$currency] = ($expected[$currency] ?? 0) + (int) $amount;
        }

        return $expected;
    }

    /**
     * @param  array<string, int>  $declared
     * @param  array<string, int>  $expected
     * @return array<string, int>
     */
    private function computeVariance(array $declared, array $expected): array
    {
        $currencies = array_unique([...array_keys($declared), ...array_keys($expected)]);
        $variance = [];

        foreach ($currencies as $currency) {
            $variance[$currency] = ($declared[$currency] ?? 0) - ($expected[$currency] ?? 0);
        }

        return $variance;
    }

    /**
     * @param  array<string, int>  $variance
     * @return array<int, JournalLineData>
     */
    private function varianceJournalLines(Till $till, array $variance, int $cashOverShortAccountId): array
    {
        $lines = [];

        foreach ($variance as $currencyCode => $amount) {
            if ($amount === 0) {
                continue;
            }

            $currency = Currency::from($currencyCode);
            $money = Money::of(abs($amount), $currency);

            // Shortage (declared < expected): Dr Cash Over/Short, Cr Cash.
            // Overage (declared > expected): Dr Cash, Cr Cash Over/Short.
            $lines[] = new JournalLineData(
                accountId: $amount < 0 ? $cashOverShortAccountId : $till->cash_account_id,
                direction: 'DR',
                amount: $money,
                narration: 'Till variance',
            );

            $lines[] = new JournalLineData(
                accountId: $amount < 0 ? $till->cash_account_id : $cashOverShortAccountId,
                direction: 'CR',
                amount: $money,
                narration: 'Till variance',
            );
        }

        return $lines;
    }
}
