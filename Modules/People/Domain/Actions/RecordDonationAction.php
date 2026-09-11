<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\People\Domain\DataObjects\RecordDonationData;
use Modules\People\Domain\DataObjects\SyncEndowmentBudgetEnvelopeData;
use Modules\People\Models\CapitalCampaign;
use Modules\People\Models\Donation;
use Modules\People\Models\Pledge;

/**
 * ACT-RecordDonation (Book K PPL-06 §4/BR-PPL-06-006/007/008 ⭐/010/
 * AC-PPL-06-003). Posts `Dr Bank / Cr Donation Income` (or a
 * restricted fund account) directly through `PostJournalAction` — see
 * the `donations` migration's docblock for why this bypasses `FIN-04`'s
 * `CreateReceiptAction` entirely (a donor isn't a fee-paying student,
 * so there's no invoice to allocate against). A campaign's
 * `raised_amount_minor` and a pledge's `paid_to_date_minor` are only
 * ever moved here — never directly editable — so campaign progress
 * always reflects real receipts, not stated intentions
 * (BR-PPL-06-010/AC-PPL-06-003).
 */
final class RecordDonationAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly SyncEndowmentBudgetEnvelopeAction $syncEnvelope,
    ) {}

    public function execute(RecordDonationData $data): Donation
    {
        $campaign = $data->campaignId !== null ? CapitalCampaign::findOrFail($data->campaignId) : null;
        $incomeAccountId = $data->incomeAccountId ?? $campaign?->income_account_id;

        if ($incomeAccountId === null) {
            throw new InvalidArgumentException('A donation needs an income account — either directly, or via its campaign.');
        }

        $currency = Currency::from($data->currency);
        $amount = Money::of($data->amountMinor, $currency);
        $receivedAt = $data->receivedAt ?? Carbon::now();

        return $this->transaction(function () use ($data, $campaign, $incomeAccountId, $currency, $amount, $receivedAt): Donation {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'DONATION_RECEIVED',
                narration: "Donation received — {$data->donorName}",
                lines: [
                    new JournalLineData(accountId: $data->bankAccountId, direction: 'DR', amount: $amount),
                    new JournalLineData(accountId: $incomeAccountId, direction: 'CR', amount: $amount),
                ],
                effectiveAt: $receivedAt,
                postedByUserId: $data->recordedByUserId,
                sourceType: 'alumni_donation',
            ));

            $donation = Donation::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'pledge_id' => $data->pledgeId,
                'bursary_endowment_id' => $data->bursaryEndowmentId,
                'donor_name' => $data->donorName,
                'amount_minor' => $data->amountMinor,
                'currency' => $currency->value,
                'received_at' => $receivedAt,
                'journal_id' => $journal->id,
                'is_restricted' => $data->isRestricted,
                'restriction_purpose' => $data->restrictionPurpose,
            ]);

            if ($campaign !== null) {
                $campaign->increment('raised_amount_minor', $data->amountMinor);
            }

            if ($data->pledgeId !== null) {
                $this->applyToPledge($data->pledgeId, $data->amountMinor);
            }

            if ($data->bursaryEndowmentId !== null) {
                $this->syncEnvelope->execute(new SyncEndowmentBudgetEnvelopeData(
                    bursaryEndowmentId: $data->bursaryEndowmentId,
                    academicYearId: $data->academicYearId,
                ));
            }

            return $donation->fresh();
        });
    }

    private function applyToPledge(int $pledgeId, int $amountMinor): void
    {
        $pledge = Pledge::findOrFail($pledgeId);
        $paidToDate = $pledge->paid_to_date_minor + $amountMinor;

        $pledge->update([
            'paid_to_date_minor' => $paidToDate,
            'status' => $paidToDate >= $pledge->pledged_amount_minor ? 'completed' : 'fulfilling',
        ]);
    }
}
