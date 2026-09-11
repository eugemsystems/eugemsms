<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\IssueLicenceKeyData;
use Modules\Saas\Models\LicenceKey;

/**
 * ACT-IssueLicenceKey (Book J SAA-01 §2/§4/BR-SAA-01-007). Vendor
 * console issues an on-premise licence key for a subscription.
 */
final class IssueLicenceKeyAction extends Action
{
    public function execute(IssueLicenceKeyData $data): LicenceKey
    {
        return $this->transaction(fn (): LicenceKey => LicenceKey::create([
            'tenant_id' => $data->tenantId,
            'subscription_id' => $data->subscriptionId,
            'key_value' => 'SERP-'.mb_strtoupper(Str::random(4)).'-'.mb_strtoupper(Str::random(4)).'-'.mb_strtoupper(Str::random(4)),
            'offline_grace_days' => $data->offlineGraceDays,
            'status' => 'active',
        ]));
    }
}
