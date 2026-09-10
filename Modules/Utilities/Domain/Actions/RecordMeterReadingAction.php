<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Utilities\Domain\DataObjects\RecordMeterReadingData;
use Modules\Utilities\Domain\Events\MeterReadingAnomaly;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\MeterReading;
use Modules\Utilities\Models\UtilityAccount;

/**
 * ACT-RecordMeterReading (Book H2 OPS-04 §2 ⭐/BR-OPS-04-004/006/007/
 * 008/009/AC-OPS-04-004). Append-only — a correction is a new row,
 * never an edit. A reading lower than the previous one is always an
 * anomaly; consumption beyond the rolling-average tolerance is too. A
 * submetered reading's expense allocates to the meter's own cost
 * centre (BR-OPS-04-009), and — for a prepaid meter — recognises real
 * consumption expense here rather than at token purchase
 * (BR-OPS-04-004).
 */
final class RecordMeterReadingAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly PostJournalAction $postJournal,
    ) {}

    public function execute(RecordMeterReadingData $data): MeterReading
    {
        $meter = Meter::findOrFail($data->meterId);
        $previous = MeterReading::where('meter_id', $meter->id)->orderByDesc('read_on')->first();

        $previousReading = $previous?->reading;
        $consumption = null;
        $daysSinceLast = null;
        $dailyAverage = null;
        $reasons = [];

        if ($previousReading !== null) {
            if ($data->reading < (float) $previousReading) {
                $reasons[] = 'Reading is lower than the previous reading — check for meter replacement, rollover or misreading (BR-OPS-04-007).';
            } else {
                $consumption = ($data->reading - (float) $previousReading) * (float) $meter->multiplier;
                $daysSinceLast = (int) $previous->read_on->diffInDays($data->readOn);
                $dailyAverage = $daysSinceLast > 0 ? $consumption / $daysSinceLast : null;

                if ($dailyAverage !== null) {
                    $tolerancePercent = (float) $this->settings->get('utilities.consumption_anomaly_tolerance_percent', new ScopeChain(schoolId: $data->schoolId));
                    $baseline = $this->rollingAverageDailyConsumption($meter->id, $previous->id);

                    if ($baseline !== null && $baseline > 0) {
                        $variancePercent = (($dailyAverage - $baseline) / $baseline) * 100;

                        if (abs($variancePercent) > $tolerancePercent) {
                            $reasons[] = sprintf('Daily consumption varies %.1f%% from the rolling average, beyond the %d%% tolerance (BR-OPS-04-008).', $variancePercent, (int) $tolerancePercent);
                        }
                    }
                }
            }
        }

        $isAnomaly = $reasons !== [];

        return $this->transaction(function () use ($data, $meter, $previousReading, $consumption, $daysSinceLast, $dailyAverage, $isAnomaly, $reasons): MeterReading {
            $reading = MeterReading::create([
                'school_id' => $data->schoolId,
                'meter_id' => $meter->id,
                'read_on' => $data->readOn->toDateString(),
                'reading' => $data->reading,
                'previous_reading' => $previousReading,
                'consumption' => $consumption,
                'days_since_last' => $daysSinceLast,
                'daily_average' => $dailyAverage,
                'reading_method' => $data->readingMethod,
                'photo_file_id' => $data->photoFileId,
                'read_by' => $data->readByUserId,
                'is_anomaly' => $isAnomaly,
                'anomaly_note' => $isAnomaly ? implode(' ', $reasons) : null,
            ]);

            $meter->update(['current_reading' => $data->reading, 'last_read_on' => $data->readOn->toDateString()]);

            if ($isAnomaly) {
                event(new MeterReadingAnomaly($reading, implode(' ', $reasons)));
            }

            if ($consumption !== null && $consumption > 0) {
                $this->recogniseConsumptionExpense($data, $meter, $consumption);
            }

            return $reading;
        });
    }

    private function rollingAverageDailyConsumption(int $meterId, int $excludingReadingId): ?float
    {
        $recent = MeterReading::where('meter_id', $meterId)
            ->where('id', '!=', $excludingReadingId)
            ->whereNotNull('daily_average')
            ->orderByDesc('read_on')
            ->limit(3)
            ->pluck('daily_average');

        if ($recent->isEmpty()) {
            return null;
        }

        return (float) $recent->avg();
    }

    private function recogniseConsumptionExpense(RecordMeterReadingData $data, Meter $meter, float $consumption): void
    {
        $account = UtilityAccount::find($meter->utility_account_id);

        if ($account === null || $account->billing_mode !== 'prepaid' || $data->prepaidAssetAccountId === null
            || $data->academicYearId === null || $data->termId === null || $data->postedByUserId === null) {
            return;
        }

        $unitCostMinor = $this->estimatedUnitCostMinor($meter);

        if ($unitCostMinor === null) {
            return;
        }

        $amountMinor = (int) round($consumption * $unitCostMinor);

        if ($amountMinor <= 0) {
            return;
        }

        $currency = Currency::from($this->purchaseCurrency($meter) ?? 'USD');

        $this->postJournal->execute(new PostJournalData(
            schoolId: $data->schoolId,
            academicYearId: $data->academicYearId,
            termId: $data->termId,
            journalType: 'UTILITY_CONSUMPTION_EXPENSE',
            narration: "Electricity consumption — {$meter->meter_number}",
            lines: [
                new JournalLineData(accountId: $account->expense_account_id, direction: 'DR', amount: Money::of($amountMinor, $currency), costCentreId: $meter->cost_centre_id ?? $account->cost_centre_id),
                new JournalLineData(accountId: $data->prepaidAssetAccountId, direction: 'CR', amount: Money::of($amountMinor, $currency)),
            ],
            effectiveAt: $data->readOn,
            postedByUserId: $data->postedByUserId,
            sourceType: 'meter_reading',
        ));
    }

    private function estimatedUnitCostMinor(Meter $meter): ?int
    {
        $lastCredited = $meter->tokenPurchases()->where('credit_confirmed', true)->orderByDesc('purchased_at')->first();

        return $lastCredited?->effective_rate_minor;
    }

    private function purchaseCurrency(Meter $meter): ?string
    {
        return $meter->tokenPurchases()->where('credit_confirmed', true)->orderByDesc('purchased_at')->value('currency');
    }
}
