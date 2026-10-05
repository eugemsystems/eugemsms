<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\IncidentStatusEntry;

/**
 * ACT-GetPublicStatus (Book J SAA-02 §4, `GET /status`). The only read the
 * unauthenticated status page performs: public incidents that are still
 * open, plus those resolved in the last seven days — never a private
 * incident, and only the fields a customer needs.
 */
final class GetPublicStatusAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return Collection<int, IncidentStatusEntry>
     */
    public function execute(): Collection
    {
        return IncidentStatusEntry::query()
            ->where('is_public', true)
            ->where(fn ($q) => $q->where('status', '!=', 'resolved')->orWhere('updated_at', '>=', Carbon::now()->subDays(7)))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'affected_components', 'severity', 'status', 'updates', 'created_at', 'updated_at']);
    }
}
