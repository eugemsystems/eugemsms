<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\People\Domain\DataObjects\CreatePledgeData;
use Modules\People\Models\Alumnus;
use Modules\People\Models\CapitalCampaign;
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
        if (trim($data->donorName) === '' || mb_strlen($data->donorName) > 200) {
            throw new InvalidArgumentException('A pledge needs the donor’s name.');
        }

        if (! in_array($data->donorType, ['alumnus', 'parent', 'staff', 'corporate', 'foundation'], true)
            || ($data->recognitionTier !== null && ! in_array($data->recognitionTier, ['bronze', 'silver', 'gold', 'platinum'], true))) {
            throw new InvalidArgumentException('That donor type or recognition tier is not recognised.');
        }

        if ($data->pledgedAmountMinor <= 0 || Currency::tryFrom($data->currency) === null) {
            throw new InvalidArgumentException('A pledge needs a positive amount in a supported currency.');
        }

        if ($data->campaignId !== null) {
            $campaign = CapitalCampaign::query()->where('school_id', $data->schoolId)->findOrFail($data->campaignId);

            if ($campaign->status !== 'active' || $campaign->currency !== $data->currency) {
                throw new InvalidArgumentException('A pledge must be to an active campaign, in that campaign’s currency.');
            }
        }

        if ($data->alumnusId !== null) {
            Alumnus::query()->where('school_id', $data->schoolId)->findOrFail($data->alumnusId);
        }

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
