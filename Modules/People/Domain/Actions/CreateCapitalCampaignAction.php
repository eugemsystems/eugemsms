<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Finance\Models\Account;
use Modules\People\Domain\DataObjects\CreateCapitalCampaignData;
use Modules\People\Models\CapitalCampaign;

final class CreateCapitalCampaignAction extends Action
{
    public function execute(CreateCapitalCampaignData $data): CapitalCampaign
    {
        if (trim($data->name) === '' || mb_strlen($data->name) > 150 || trim($data->purpose) === '') {
            throw new InvalidArgumentException('A campaign needs a name (up to 150 characters) and a purpose.');
        }

        if ($data->targetAmountMinor <= 0 || Currency::tryFrom($data->currency) === null) {
            throw new InvalidArgumentException('A campaign needs a positive target in a supported currency.');
        }

        if ($data->endsOn !== null && $data->endsOn->lessThan($data->startsOn)) {
            throw new InvalidArgumentException('A campaign cannot end before it starts.');
        }

        // Donations post to this account, so it must be the school's own postable income account.
        Account::query()->where('school_id', $data->schoolId)->where('is_postable', true)->findOrFail($data->incomeAccountId);

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
