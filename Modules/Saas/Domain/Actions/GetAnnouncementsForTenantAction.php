<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\BroadcastAnnouncement;

/**
 * ACT-GetAnnouncementsForTenant (Book J SAA-02 §3/BR-SAA-02-006). What the
 * vendor has broadcast to ONE tenant's administrators right now: every
 * active announcement addressed to all tenants, plus those that name this
 * tenant explicitly. Targeting is never inferred.
 */
final class GetAnnouncementsForTenantAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, BroadcastAnnouncement>
     */
    public function execute(int $tenantId): Collection
    {
        $now = Carbon::now();

        return BroadcastAnnouncement::query()
            ->where('starts_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get()
            ->filter(fn (BroadcastAnnouncement $a): bool => $a->targets($tenantId))
            ->values();
    }
}
