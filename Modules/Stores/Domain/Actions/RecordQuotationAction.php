<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\RecordQuotationData;
use Modules\Stores\Models\Quotation;
use Modules\Stores\Models\QuotationLine;

final class RecordQuotationAction extends Action
{
    public function execute(RecordQuotationData $data): Quotation
    {
        return $this->transaction(function () use ($data): Quotation {
            $quotation = Quotation::create([
                'school_id' => $data->schoolId,
                'quotation_request_id' => $data->quotationRequestId,
                'supplier_id' => $data->supplierId,
                'quotation_reference' => $data->quotationReference,
                'received_on' => $data->receivedOn->toDateString(),
                'subtotal_minor' => $data->subtotalMinor,
                'tax_minor' => $data->taxMinor,
                'total_minor' => $data->totalMinor,
                'currency' => $data->currency,
                'delivery_days' => $data->deliveryDays,
                'payment_terms_days' => $data->paymentTermsDays,
                'is_compliant' => $data->isCompliant,
                'non_compliance_note' => $data->nonComplianceNote,
                'status' => 'received',
            ]);

            foreach ($data->lines as $line) {
                QuotationLine::create([
                    'quotation_id' => $quotation->id,
                    'requisition_line_id' => $line['requisitionLineId'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $line['unitPriceMinor'],
                    'line_total_minor' => (int) round($line['quantity'] * $line['unitPriceMinor']),
                    'lead_time_days' => $line['leadTimeDays'],
                ]);
            }

            return $quotation;
        });
    }
}
