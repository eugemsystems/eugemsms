<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Fiscal\Domain\DataObjects\OpenFiscalDayData;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalisationRule;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * ACT-RouteReceiptForFiscalisation (Book H3 FIN-13 §4 ⭐ — the
 * cardinal rule/BR-FIN-13-001/002/003/004/005). Pure, cheap local
 * writes only — no network call happens here, which is exactly why
 * this can never block a receipt. Evaluates each line against
 * `fiscalisation_rules` by `(source_type, source_identifier)`,
 * highest `priority` active rule wins; a line with no matching rule,
 * or a matched rule with `is_fiscalisable = false`, is left out of
 * the fiscal receipt entirely (AC-FIN-13-004's "only the uniform line
 * is fiscalised"). No fiscalisable line at all → returns `null`
 * (`not_required`), no row created. `SubmitFiscalReceiptAction` is
 * called synchronously at the end for the common case, but ANY
 * failure there — including `FdmsUnreachableException` — is
 * swallowed by that action itself (never rethrown here), so this
 * action's own success never depends on FDMS being reachable.
 */
final class RouteReceiptForFiscalisationAction extends Action
{
    public function __construct(
        private readonly SubmitFiscalReceiptAction $submitReceipt,
        private readonly OpenFiscalDayAction $openFiscalDay,
    ) {}

    public function execute(RouteReceiptForFiscalisationData $data): ?FiscalReceipt
    {
        $device = FiscalDevice::where('school_id', $data->schoolId)->where('is_active', true)->first();

        if ($device === null) {
            return null;
        }

        $fiscalLines = [];
        $totalMinor = 0;
        $taxBreakdown = [];

        foreach ($data->lines as $line) {
            $rule = $this->matchRule($data->schoolId, $data->sourceType, $line['source_identifier']);

            if ($rule === null || ! $rule->is_fiscalisable) {
                continue;
            }

            $fiscalLines[] = $line;
            $totalMinor += $line['amount_minor'];

            $taxKey = $rule->tax_type;
            $taxBreakdown[$taxKey] ??= ['base_minor' => 0, 'rate_percent' => (float) $rule->tax_rate_percent, 'tax_minor' => 0];
            $taxBreakdown[$taxKey]['base_minor'] += $line['amount_minor'];
            $taxBreakdown[$taxKey]['tax_minor'] += (int) round($line['amount_minor'] * ((float) $rule->tax_rate_percent / 100));
        }

        if ($fiscalLines === []) {
            return null;
        }

        $day = FiscalDay::where('device_id', $device->id)->where('local_status', 'open')->first();

        if ($day === null) {
            $day = $this->openFiscalDay->execute(new OpenFiscalDayData(deviceId: $device->id, openedByUserId: $data->performedByUserId));
        }

        $fiscalReceipt = $this->transaction(function () use ($device, $day, $data, $totalMinor, $taxBreakdown, $fiscalLines): FiscalReceipt {
            $receiptCounter = (int) FiscalReceipt::where('device_id', $device->id)
                ->where('receipt_currency', $data->currency)
                ->max('receipt_counter') + 1;

            $globalCounter = (int) FiscalReceipt::where('device_id', $device->id)->max('global_counter') + 1;

            $previousHash = FiscalReceipt::where('device_id', $device->id)
                ->orderByDesc('global_counter')
                ->value('receipt_hash');

            return FiscalReceipt::create([
                'school_id' => $data->schoolId,
                'device_id' => $device->id,
                'fiscal_day_id' => $day->id,
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'receipt_type' => $data->receiptType,
                'receipt_currency' => $data->currency,
                'receipt_counter' => $receiptCounter,
                'global_counter' => $globalCounter,
                'invoice_number' => $data->invoiceNumber,
                'receipt_date' => $data->receiptDate,
                'total_minor' => $totalMinor,
                'tax_breakdown' => $taxBreakdown,
                'payment_methods' => $data->paymentMethods,
                'buyer_name' => $data->buyerName,
                'buyer_tin' => $data->buyerTin,
                'previous_receipt_hash' => $previousHash,
                'status' => 'queued',
                'payload' => [
                    'invoice_number' => $data->invoiceNumber,
                    'receipt_counter' => $receiptCounter,
                    'global_counter' => $globalCounter,
                    'currency' => $data->currency,
                    'total_minor' => $totalMinor,
                    'tax_breakdown' => $taxBreakdown,
                    'lines' => $fiscalLines,
                    'payment_methods' => $data->paymentMethods,
                    'previous_receipt_hash' => $previousHash,
                    '_simulate' => $data->simulate,
                ],
            ]);
        });

        $this->submitReceipt->execute($fiscalReceipt->id);

        return $fiscalReceipt->fresh();
    }

    private function matchRule(int $schoolId, string $sourceType, string $sourceIdentifier): ?FiscalisationRule
    {
        return FiscalisationRule::where('school_id', $schoolId)
            ->where('source_type', $sourceType)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('source_identifier', $sourceIdentifier)->orWhereNull('source_identifier'))
            ->orderByRaw('source_identifier IS NULL')
            ->orderBy('priority')
            ->first();
    }
}
