<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Utilities\Domain\DataObjects\PurchasePrepaidTokenData;
use Modules\Utilities\Domain\Events\TokenPurchased;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * ACT-PurchasePrepaidToken (Book H2 OPS-04 §2/§3 🇿🇼 ⭐/BR-OPS-04-001/
 * 003/004). Posts `Dr Prepaid Electricity (asset) / Cr <contra>` —
 * the purchase itself is never an expense (BR-OPS-04-004); expense
 * recognises later, on consumption, in `RecordMeterReadingAction`. A
 * token starts `purchased`/`credit_confirmed = false` and stays that
 * way until a human confirms it was actually loaded — the real
 * control this rule exists for.
 */
final class PurchasePrepaidTokenAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(PurchasePrepaidTokenData $data): PrepaidTokenPurchase
    {
        if (PrepaidTokenPurchase::where('school_id', $data->schoolId)->where('token_number', $data->tokenNumber)->exists()) {
            throw ValidationException::withMessages([
                'tokenNumber' => "Token {$data->tokenNumber} has already been recorded for this school (BR-OPS-04-003).",
            ]);
        }

        $currency = Currency::from($data->currency);
        $effectiveRateMinor = $data->unitsPurchased > 0
            ? (int) round($data->amountPaidMinor / $data->unitsPurchased)
            : null;

        return $this->transaction(function () use ($data, $currency, $effectiveRateMinor): PrepaidTokenPurchase {
            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'UTILITY_TOKEN_PURCHASE',
                narration: "Prepaid token purchase — {$data->tokenNumber}",
                lines: [
                    new JournalLineData(accountId: $data->prepaidAssetAccountId, direction: 'DR', amount: Money::of($data->amountPaidMinor, $currency)),
                    new JournalLineData(accountId: $data->contraAccountId, direction: 'CR', amount: Money::of($data->amountPaidMinor, $currency)),
                ],
                effectiveAt: $data->purchasedAt,
                postedByUserId: $data->purchasedByUserId,
                sourceType: 'prepaid_token_purchase',
            ));

            $purchase = PrepaidTokenPurchase::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'meter_id' => $data->meterId,
                'purchased_at' => $data->purchasedAt,
                'token_number' => $data->tokenNumber,
                'amount_paid_minor' => $data->amountPaidMinor,
                'currency' => $data->currency,
                'units_purchased' => $data->unitsPurchased,
                'levies_minor' => $data->leviesMinor,
                'effective_rate_minor' => $effectiveRateMinor,
                'vendor' => $data->vendor,
                'purchased_by' => $data->purchasedByUserId,
                'credit_confirmed' => false,
                'status' => 'purchased',
                'journal_id' => $journal->id,
            ]);

            event(new TokenPurchased($purchase));

            return $purchase;
        });
    }
}
