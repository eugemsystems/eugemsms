<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreatePledgeData;
use Modules\People\Models\Pledge;

/**
 * ACT-CreatePledge (Book K PPL-06 §4/BR-PPL-06-006). A stated
 * intention only — nothing here touches the ledger; only
 * `RecordDonationAction` ever moves `paid_to_date_minor`.
 */
final class CreatePledgeAction extends Action
{
    public function execute(CreatePledgeData $data): Pledge
    {
        return $this->transaction(fn (): Pledge => Pledge::create([
            'school_id' => $data->schoolId,
            'campaign_id' => $data->campaignId,
            'alumnus_id' => $data->alumnusId,
            'donor_name' => $data->donorName,
            'donor_type' => $data->donorType,
            'pledged_amount_minor' => $data->pledgedAmountMinor,
            'currency' => $data->currency,
            'schedule' => $data->schedule,
            'paid_to_date_minor' => 0,
            'recognition_tier' => $data->recognitionTier,
            'is_anonymous' => $data->isAnonymous,
            'status' => 'pledged',
        ]));
    }
}
