<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateCapitalCampaignData;
use Modules\People\Models\CapitalCampaign;

final class CreateCapitalCampaignAction extends Action
{
    public function execute(CreateCapitalCampaignData $data): CapitalCampaign
    {
        return $this->transaction(fn (): CapitalCampaign => CapitalCampaign::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'purpose' => $data->purpose,
            'target_amount_minor' => $data->targetAmountMinor,
            'raised_amount_minor' => 0,
            'currency' => $data->currency,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'income_account_id' => $data->incomeAccountId,
            'status' => 'active',
        ]));
    }
}
