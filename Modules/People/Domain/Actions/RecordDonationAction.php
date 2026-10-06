<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Finance\Models\Account;
use Modules\People\Domain\DataObjects\RecordDonationData;
use Modules\People\Domain\DataObjects\SyncEndowmentBudgetEnvelopeData;
use Modules\People\Models\BursaryEndowment;
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
        $currency = Currency::tryFrom($data->currency);

        if ($data->amountMinor <= 0 || $currency === null || trim($data->donorName) === '') {
            throw new InvalidArgumentException('A donation needs a donor, a positive amount and a supported currency.');
        }

        if ($data->isRestricted && ($data->restrictionPurpose === null || trim($data->restrictionPurpose) === '')) {
            throw new InvalidArgumentException('A restricted donation needs its stated purpose.');
        }

        if ($data->receivedAt !== null && $data->receivedAt->isFuture()) {
            throw new InvalidArgumentException('A donation cannot be received in the future.');
        }

        Term::query()->where('school_id', $data->schoolId)->where('academic_year_id', $data->academicYearId)->findOrFail($data->termId);
        Account::query()->where('school_id', $data->schoolId)->where('is_postable', true)->findOrFail($data->bankAccountId);

        if ($data->incomeAccountId !== null) {
            Account::query()->where('school_id', $data->schoolId)->where('is_postable', true)->findOrFail($data->incomeAccountId);
        }

        $pledge = $data->pledgeId === null ? null : Pledge::query()->where('school_id', $data->schoolId)->findOrFail($data->pledgeId);

        if ($pledge !== null && ($pledge->currency !== $currency->value || $pledge->status === 'lapsed')) {
            throw new InvalidArgumentException('A donation must be in its pledge’s currency, and a lapsed pledge takes none.');
        }

        // A donation toward a campaign pledge counts toward that campaign — progress is derived
        // from donations (BR-PPL-06-010), so it must follow the pledge, never be left to the caller.
        $campaignId = $data->campaignId ?? $pledge?->campaign_id;

        if ($data->campaignId !== null && $pledge?->campaign_id !== null && $pledge->campaign_id !== $data->campaignId) {
            throw new InvalidArgumentException('That pledge belongs to a different campaign.');
        }

        $campaign = $campaignId !== null ? CapitalCampaign::query()->where('school_id', $data->schoolId)->findOrFail($campaignId) : null;

        if ($campaign !== null && ($campaign->status !== 'active' || $campaign->currency !== $currency->value)) {
            throw new InvalidArgumentException('Donations can only be recorded to an active campaign, in its currency.');
        }

        if ($data->bursaryEndowmentId !== null) {
            $endowment = BursaryEndowment::query()->where('school_id', $data->schoolId)->findOrFail($data->bursaryEndowmentId);

            if ($endowment->status !== 'active' || $endowment->currency !== $currency->value) {
                throw new InvalidArgumentException('An endowment takes donations only while active, in its own currency.');
            }
        }
        $incomeAccountId = $data->incomeAccountId ?? $campaign?->income_account_id;

        if ($incomeAccountId === null) {
            throw new InvalidArgumentException('A donation needs an income account — either directly, or via its campaign.');
        }

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
