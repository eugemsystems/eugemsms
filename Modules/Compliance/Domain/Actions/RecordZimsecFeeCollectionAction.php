<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\RecordZimsecFeeCollectionData;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordZimsecFeeCollection (Book H3 CMP-01 §3/BR-CMP-01-007).
 * `collected_minor` is an explicit bursar-recorded figure, not derived
 * from the GL — see the module's own migration docblock for why: FIN-02
 * never invoices or posts an ad hoc charge anywhere in this codebase
 * yet, so there is nothing authoritative to derive it from.
 */
final class RecordZimsecFeeCollectionAction extends Action
{
    public function execute(RecordZimsecFeeCollectionData $data): ZimsecRegistration
    {
        return $this->transaction(function () use ($data): ZimsecRegistration {
            $registration = ZimsecRegistration::findOrFail($data->registrationId);
            $registration->update(['collected_minor' => $registration->collected_minor + $data->amountMinor]);

            return $registration;
        });
    }
}
