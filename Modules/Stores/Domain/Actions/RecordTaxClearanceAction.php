<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\RecordTaxClearanceData;
use Modules\Stores\Models\SupplierTaxClearance;

/**
 * ACT-RecordTaxClearance (Book H1 FIN-08 §6/§3 ⭐ — 🇿🇼 ITF263). A new
 * certificate never mutates an earlier one's own `status` — validity
 * is assessed on the INVOICE date (BR-FIN-08-003 ⭐), so an old,
 * genuinely-valid-at-the-time certificate must stay queryable as such
 * for a historical invoice, even after a newer certificate has since
 * arrived. `SupplierTaxClearance::isValidOn()` is what actually
 * decides validity for a given date, never a single "current" flag.
 */
final class RecordTaxClearanceAction extends Action
{
    public function execute(RecordTaxClearanceData $data): SupplierTaxClearance
    {
        return $this->transaction(fn (): SupplierTaxClearance => SupplierTaxClearance::create([
            'school_id' => $data->schoolId,
            'supplier_id' => $data->supplierId,
            'certificate_number' => $data->certificateNumber,
            'issued_on' => $data->issuedOn->toDateString(),
            'expires_on' => $data->expiresOn->toDateString(),
            'verified_by' => $data->verifiedByUserId,
            'verified_at' => $data->verifiedByUserId !== null ? Carbon::now() : null,
            'verification_method' => $data->verificationMethod,
            'status' => 'valid',
        ]));
    }
}
