<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\IssueLicenceKeyData;
use Modules\Saas\Models\LicenceKey;
use Modules\Saas\Models\Subscription;

/**
 * ACT-IssueLicenceKey (Book J SAA-01 §2/§4/BR-SAA-01-007). Vendor
 * console issues an on-premise licence key for a subscription.
 */
final class IssueLicenceKeyAction extends Action
{
    public function execute(IssueLicenceKeyData $data): LicenceKey
    {
        if ($data->offlineGraceDays < 0 || $data->offlineGraceDays > 90) {
            throw new InvalidArgumentException('Offline grace must be between 0 and 90 days.');
        }

        // A key is bound to the tenant's own subscription — never another tenant's.
        Subscription::query()->where('tenant_id', $data->tenantId)->findOrFail($data->subscriptionId);

        return $this->transaction(fn (): LicenceKey => LicenceKey::create([
            'tenant_id' => $data->tenantId,
            'subscription_id' => $data->subscriptionId,
            'key_value' => 'SERP-'.mb_strtoupper(Str::random(4)).'-'.mb_strtoupper(Str::random(4)).'-'.mb_strtoupper(Str::random(4)),
            'offline_grace_days' => $data->offlineGraceDays,
            'status' => 'active',
        ]));
    }
}
