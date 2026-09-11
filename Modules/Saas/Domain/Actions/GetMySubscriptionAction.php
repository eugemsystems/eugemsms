<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\Subscription;

/**
 * ACT-GetMySubscription (Book J SAA-01 §5/BR-SAA-01-001
 * ⭐/AC-SAA-01-006). The narrow "my subscription" read path — every
 * query here is filtered by the caller's OWN `tenantId`, never a
 * lookup by subscription id alone, so a tenant can never resolve
 * another tenant's plan, usage, or invoices by guessing an id.
 */
final class GetMySubscriptionAction extends Action
{
    public function execute(int $tenantId): ?Subscription
    {
        return Subscription::query()
            ->where('tenant_id', $tenantId)
            ->with(['plan', 'invoices'])
            ->latest('id')
            ->first();
    }
}
