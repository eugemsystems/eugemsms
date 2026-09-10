<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\Events\DepreciationPosted;
use Modules\Stores\Models\DepreciationRun;

/**
 * ACT-PostDepreciationRun (Book H1 FIN-10 §6/BR-FIN-10-004/006/007).
 * One journal for the whole run, lines grouped by category and cost
 * centre on the expense side (BR-FIN-10-004) — the same "one
 * transaction, many lines, aggregated" discipline `FIN-09`'s
 * `IssueStockAction` established for this codebase. `DepreciationRun`
 * carries `term_id`, so the model's own `PeriodGuard` call refuses
 * this on a locked period (BR-FIN-10-007) automatically.
 */
final class PostDepreciationRunAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(int $runId, int $postedByUserId): DepreciationRun
    {
        $run = DepreciationRun::with('entries.asset.category')->findOrFail($runId);

        if ($run->status !== 'approved') {
            throw new InvalidStateTransitionException(
                "Depreciation run #{$run->id} must be approved before posting (currently {$run->status}).",
                ['run_id' => $run->id, 'status' => $run->status],
            );
        }

        $currency = Currency::from($run->currency);

        return $this->transaction(function () use ($run, $currency, $postedByUserId): DepreciationRun {
            /** @var array<string, array{accountId: int, costCentreId: int|null, amount: Money}> $debitLines */
            $debitLines = [];
            /** @var array<int, array{accountId: int, amount: Money}> $creditLines */
            $creditLines = [];

            foreach ($run->entries as $entry) {
                $asset = $entry->asset;
                $category = $asset->category;

                $debitKey = $category->depreciation_expense_account_id.'|'.$asset->cost_centre_id;
                $debitLines[$debitKey] ??= ['accountId' => $category->depreciation_expense_account_id, 'costCentreId' => $asset->cost_centre_id, 'amount' => Money::zero($currency)];
                $debitLines[$debitKey]['amount'] = $debitLines[$debitKey]['amount']->plus(Money::of($entry->depreciation_minor, $currency));

                $creditKey = $category->accum_depreciation_account_id;
                $creditLines[$creditKey] ??= ['accountId' => $creditKey, 'amount' => Money::zero($currency)];
                $creditLines[$creditKey]['amount'] = $creditLines[$creditKey]['amount']->plus(Money::of($entry->depreciation_minor, $currency));
            }

            $journalLines = array_values(array_map(
                fn (array $l): JournalLineData => new JournalLineData(accountId: $l['accountId'], direction: 'DR', amount: $l['amount'], costCentreId: $l['costCentreId']),
                $debitLines,
            ));

            foreach ($creditLines as $l) {
                $journalLines[] = new JournalLineData(accountId: $l['accountId'], direction: 'CR', amount: $l['amount']);
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $run->school_id,
                academicYearId: $run->academic_year_id,
                termId: $run->term_id,
                journalType: 'DEPRECIATION',
                narration: "Depreciation run {$run->period_month}",
                lines: $journalLines,
                effectiveAt: $run->run_date,
                postedByUserId: $postedByUserId,
                sourceType: 'depreciation_run',
                sourceId: $run->id,
            ));

            foreach ($run->entries as $entry) {
                $asset = $entry->asset;
                $newAccumulated = $asset->accumulated_depreciation_minor + $entry->depreciation_minor;
                $newNbv = max($asset->residual_value_minor, $entry->closing_nbv_minor);

                $asset->update([
                    'accumulated_depreciation_minor' => $newAccumulated,
                    'net_book_value_minor' => $newNbv,
                    'last_depreciated_on' => $run->run_date,
                    'fully_depreciated' => $newNbv <= $asset->residual_value_minor,
                ]);
            }

            $run->update([
                'status' => 'posted',
                'journal_id' => $journal->id,
                'posted_at' => Carbon::now(),
            ]);

            event(new DepreciationPosted($run));

            return $run;
        });
    }
}
