<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\DataObjects\CreateProviderRateCardData;
use Modules\Comms\Models\ProviderRateCard;
use Modules\Core\Domain\Actions\Action;

final class CreateProviderRateCardAction extends Action
{
    public function execute(CreateProviderRateCardData $data): ProviderRateCard
    {
        return $this->transaction(fn (): ProviderRateCard => ProviderRateCard::create([
            'school_id' => $data->schoolId,
            'gateway_id' => $data->gatewayId,
            'destination_prefix' => $data->destinationPrefix,
            'rate_per_segment_minor' => $data->ratePerSegmentMinor,
            'whatsapp_utility_rate_minor' => $data->whatsappUtilityRateMinor,
            'whatsapp_marketing_rate_minor' => $data->whatsappMarketingRateMinor,
            'currency' => $data->currency,
            'effective_from' => $data->effectiveFrom,
            'effective_to' => $data->effectiveTo,
        ]));
    }
}
